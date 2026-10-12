<?php

namespace App\Tests;

use App\Entity\AssoEvent;
use App\Entity\AssoEventInstance;
use App\Entity\FormWidget;
use App\Entity\Registration;
use App\Entity\RegistrationForm;
use App\Entity\User;
use App\Enum\FormWidgetKind;
use App\Model\FormWidgetEntry;
use App\Service\NativeWidgets;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for event registration forms.
 *
 * Fixtures are created with a unique suffix and removed in tearDown(), so tests
 * stay independent within a run (the test database is rebuilt by tests/bootstrap.php).
 */
class EventRegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $suffix;

    /** @var int[] */
    private array $eventIds = [];
    /** @var int[] */
    private array $userIds = [];
    /** @var int[] */
    private array $widgetIds = [];

    /**
     * Entry keys of the last form created by createRootWidget(): required text, optional textarea, optional choice.
     *
     * @var string[]
     */
    private array $fields = [];

    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->suffix = bin2hex(random_bytes(4));

        $this->user = $this->createUser('user', []);
        $this->admin = $this->createUser('admin', ['ROLE_ADMIN']);
    }

    protected function tearDown(): void
    {
        try {
            $this->removeFixtures();
        } finally {
            // Always shut the kernel down, otherwise the next test cannot boot it.
            parent::tearDown();
        }
    }

    private function removeFixtures(): void
    {
        static::getContainer()->get('doctrine')->resetManager();
        $em = $this->em();

        try {
            // Removing the event cascades to instances, forms and registrations.
            foreach ($this->eventIds as $id) {
                if ($event = $em->find(AssoEvent::class, $id)) {
                    $em->remove($event);
                }
            }
            $em->flush();

            // Registrations left behind (e.g. when a test deleted the instance itself).
            foreach ($em->getRepository(Registration::class)->findBy(['user' => $this->userIds]) as $registration) {
                $em->remove($registration);
            }
            $em->flush();
        } finally {
            // Raw SQL fallback, so a failing ORM cleanup never leaks fixtures into later tests.
            $connection = $em->getConnection();
            $delete = fn (string $sql, array $ids) => $ids && $connection->executeStatement($sql, [$ids], [ArrayParameterType::INTEGER]);
            $delete('DELETE FROM registration_form WHERE widget_id IN (?)', $this->widgetIds);
            $delete('DELETE FROM asso_event_instance WHERE parent_event_id IN (?)', $this->eventIds);
            $delete('DELETE FROM asso_event WHERE id IN (?)', $this->eventIds);
            $delete('DELETE FROM user WHERE id IN (?)', $this->userIds);
            // Widgets are library items, not removed with the events.
            $delete('DELETE FROM form_widget WHERE id IN (?)', $this->widgetIds);
        }
    }

    // ---------------------------------------------------------------------
    // Public: instance page
    // ---------------------------------------------------------------------

    public function testInstancePageHasNoRegistrationBlockWithoutForm(): void
    {
        $instance = $this->createInstance();

        $this->client->request('GET', $this->showUrl($instance));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="'.$this->registerUrl($instance).'"]');
        self::assertSelectorTextNotContains('main', 'Inscriptions fermées');
    }

    public function testInstancePageAsksAnonymousToLogIn(): void
    {
        $instance = $this->createInstance();
        $this->createForm($instance);

        $this->client->request('GET', $this->showUrl($instance));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Connectez-vous pour vous inscrire');
    }

    public function testInstancePageShowsRegisterLinkWhenOpen(): void
    {
        $instance = $this->createInstance();
        $this->createForm($instance);
        $this->client->loginUser($this->user);

        $this->client->request('GET', $this->showUrl($instance));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="'.$this->registerUrl($instance).'"]');
    }

    public function testInstancePageShowsClosedNotice(): void
    {
        $instance = $this->createInstance();
        $this->createForm($instance, open: false);
        $this->client->loginUser($this->user);

        $this->client->request('GET', $this->showUrl($instance));

        self::assertSelectorTextContains('main', 'Inscriptions fermées');
        self::assertSelectorNotExists('a[href="'.$this->registerUrl($instance).'"]');
    }

    public function testInstancePageShowsRegisteredStateAndCancelButton(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->createRegistration($form, $this->user);
        $this->client->loginUser($this->user);

        $this->client->request('GET', $this->showUrl($instance));

        self::assertSelectorTextContains('main', 'Vous êtes inscrit');
        self::assertSelectorExists('form[action="'.$this->cancelUrl($instance).'"] button');
    }

    // ---------------------------------------------------------------------
    // Public: registering
    // ---------------------------------------------------------------------

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $instance = $this->createInstance();
        $this->createForm($instance);

        $this->client->request('GET', $this->registerUrl($instance));

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location'));
    }

    public function testRegisterPageReturns404WithoutForm(): void
    {
        $instance = $this->createInstance();
        $this->client->loginUser($this->user);

        $this->client->request('GET', $this->registerUrl($instance));

        self::assertResponseStatusCodeSame(404);
    }

    public function testRegisterPageRendersEveryFieldType(): void
    {
        $instance = $this->createInstance();
        $this->createForm($instance, description: 'Venez nombreux');
        [$text, $textarea, $choice] = $this->fields;
        $this->client->loginUser($this->user);

        $this->client->request('GET', $this->registerUrl($instance));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Venez nombreux');
        self::assertSelectorExists(sprintf('input[type="text"][name="%s"]', $this->answerName($text)));
        self::assertSelectorExists(sprintf('textarea[name="%s"]', $this->answerName($textarea)));
        self::assertSelectorExists(sprintf('select[name="%s"] option[value="Végétarien"]', $this->answerName($choice)));
        self::assertSelectorTextContains('main', 'Nom de l\'équipe');
    }

    public function testUserCanRegister(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        [$text, $textarea, $choice] = $this->fields;
        $this->client->loginUser($this->user);

        $this->client->request('GET', $this->registerUrl($instance));
        $this->client->submitForm('S\'inscrire', [
            $this->answerName($text) => 'Les Ofnis',
            $this->answerName($textarea) => "Ligne 1\nLigne 2",
            $this->answerName($choice) => 'Végétarien',
        ]);

        self::assertResponseRedirects($this->showUrl($instance));

        $registrations = $this->registrationsFor($form);
        self::assertCount(1, $registrations);
        self::assertSame($this->user->getId(), $registrations[0]->getUser()->getId());
        self::assertInstanceOf(\DateTimeInterface::class, $registrations[0]->getCreatedAt());
        self::assertEquals([
            $text => 'Les Ofnis',
            $textarea => "Ligne 1\nLigne 2",
            $choice => 'Végétarien',
        ], $registrations[0]->getAnswers());

        // Confirmation email
        self::assertEmailCount(1);
        $email = $this->getMailerMessages()[0];
        self::assertEmailAddressContains($email, 'from', 'no-reply@ofni.asso.fr');
        self::assertEmailAddressContains($email, 'to', $this->user->getEmail());
        self::assertEmailHtmlBodyContains($email, $instance->getName());
        self::assertEmailHtmlBodyContains($email, 'Les Ofnis');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'inscription');
    }

    public function testOptionalFieldsCanBeLeftEmpty(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        [$text] = $this->fields;
        $this->client->loginUser($this->user);

        $this->client->request('GET', $this->registerUrl($instance));
        $this->client->submitForm('S\'inscrire', [
            $this->answerName($text) => 'Les Ofnis',
        ]);

        self::assertResponseRedirects($this->showUrl($instance));
        self::assertCount(1, $this->registrationsFor($form));
    }

    public function testRequiredFieldIsEnforced(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        [$text] = $this->fields;
        $this->client->loginUser($this->user);

        $this->client->request('GET', $this->registerUrl($instance));
        $this->client->submitForm('S\'inscrire', [
            $this->answerName($text) => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->registrationsFor($form));
        self::assertEmailCount(0);
    }

    public function testChoiceOutsideOptionsIsRejected(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        [$text, , $choice] = $this->fields;
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->registerUrl($instance));
        $values = $crawler->selectButton('S\'inscrire')->form()->getPhpValues();
        $values['event_registration_answer']['answers'][$text] = 'Les Ofnis';
        $values['event_registration_answer']['answers'][$choice] = 'Pizza';
        $this->client->request('POST', $this->registerUrl($instance), $values);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->registrationsFor($form));
    }

    public function testCannotRegisterTwice(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        [$text] = $this->fields;
        $this->client->loginUser($this->user);

        // Grab a valid CSRF token while not yet registered
        $crawler = $this->client->request('GET', $this->registerUrl($instance));
        $values = $crawler->selectButton('S\'inscrire')->form()->getPhpValues();
        $values['event_registration_answer']['answers'][$text] = 'Les Ofnis';

        $this->createRegistration($form, $this->user);

        $this->client->request('GET', $this->registerUrl($instance));
        self::assertSelectorTextContains('main', 'déjà inscrit');
        self::assertSelectorNotExists('form[name="event_registration_answer"]');

        $this->client->request('POST', $this->registerUrl($instance), $values);
        self::assertCount(1, $this->registrationsFor($form));
        self::assertEmailCount(0);
    }

    public function testClosedFormRefusesRegistration(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        [$text] = $this->fields;
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->registerUrl($instance));
        $values = $crawler->selectButton('S\'inscrire')->form()->getPhpValues();
        $values['event_registration_answer']['answers'][$text] = 'Les Ofnis';

        $this->setOpen($form, false);

        $this->client->request('GET', $this->registerUrl($instance));
        self::assertSelectorTextContains('main', 'Inscriptions fermées');

        $this->client->request('POST', $this->registerUrl($instance), $values);
        self::assertCount(0, $this->registrationsFor($form));
    }

    public function testPastDeadlineRefusesRegistration(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance, closesAt: new \DateTime('-1 hour'));
        $this->client->loginUser($this->user);

        $this->client->request('GET', $this->registerUrl($instance));

        self::assertSelectorTextContains('main', 'Inscriptions fermées');
        self::assertSelectorNotExists('form[name="event_registration_answer"]');
        self::assertCount(0, $this->registrationsFor($form));
    }

    public function testEveryNativeTypeAndReusedNative(): void
    {
        $instance = $this->createInstance();
        $text = $this->natives()->get(FormWidget::TYPE_TEXT);
        $form = $this->createForm($instance, widget: $this->createComposed('Tout '.$this->suffix, [
            // the same native twice, with different names
            [$text, 'first', 'Prénom', true],
            [$text, 'last', 'Nom de famille'],
            [$this->natives()->get(FormWidget::TYPE_NUMBER), 'age', 'Âge'],
            [$this->natives()->get(FormWidget::TYPE_DATE), 'arrival', 'Arrivée'],
            [$this->natives()->get(FormWidget::TYPE_TEL), 'phone', 'Téléphone'],
            [$this->natives()->get(FormWidget::TYPE_CHECKBOX), 'rules', 'J\'accepte le règlement', true],
            [$this->natives()->get(FormWidget::TYPE_CHECKBOX), 'photos', 'Photos autorisées'],
        ]));
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->registerUrl($instance));
        self::assertSelectorExists('input[type="text"][name="'.$this->answerName('first').'"][required]');
        self::assertSelectorExists('input[type="text"][name="'.$this->answerName('last').'"]:not([required])');
        self::assertSelectorExists('input[type="number"][name="'.$this->answerName('age').'"]');
        self::assertSelectorExists('input[type="date"][name="'.$this->answerName('arrival').'"]');
        self::assertSelectorExists('input[type="tel"][name="'.$this->answerName('phone').'"]');
        self::assertSelectorExists('input[type="checkbox"][name="'.$this->answerName('rules').'"]');
        self::assertSelectorTextContains('main', 'Nom de famille');

        // the required checkbox must be ticked
        $values = $crawler->selectButton('S\'inscrire')->form()->getPhpValues();
        $values['event_registration_answer']['answers']['first'] = 'Ada';
        $this->client->request('POST', $this->registerUrl($instance), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Cette case doit être cochée');

        $values['event_registration_answer']['answers'] = array_replace($values['event_registration_answer']['answers'], ['last' => 'Lovelace', 'age' => '36', 'arrival' => '2099-03-01', 'phone' => '+33 6 12 34 56 78', 'rules' => '1']);
        $this->client->request('POST', $this->registerUrl($instance), $values);
        self::assertResponseRedirects();
        $answers = $this->registrationsFor($form)[0]->getAnswers();
        self::assertSame('Ada', $answers['first']);
        self::assertSame('Lovelace', $answers['last']);
        self::assertEquals(36, $answers['age']);
        self::assertSame('2099-03-01', $answers['arrival']);
        self::assertTrue($answers['rules']);
        self::assertArrayNotHasKey('photos', $answers);

        $this->client->loginUser($this->admin);
        $this->client->request('GET', $this->listUrl($instance));
        self::assertSelectorTextContains('table tbody', '01/03/2099');
        self::assertSelectorTextContains('table tbody', 'Oui');
        self::assertSelectorTextContains('table tbody', 'Non');
    }

    public function testInvalidPhoneIsRejected(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance, widget: $this->createComposed('Tel '.$this->suffix, [
            [$this->natives()->get(FormWidget::TYPE_TEL), 'phone', 'Téléphone'],
        ]));
        $this->client->loginUser($this->user);

        $this->client->request('GET', $this->registerUrl($instance));
        $this->client->submitForm('S\'inscrire', [$this->answerName('phone') => 'appelle-moi']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Numéro de téléphone invalide');
        self::assertCount(0, $this->registrationsFor($form));
    }

    // ---------------------------------------------------------------------
    // Public: list widgets
    // ---------------------------------------------------------------------

    public function testListWidgetShowsOneEntryByDefault(): void
    {
        $instance = $this->createInstance();
        [, $list, $member, , $listWidget] = $this->createListForm($instance, min: 0);
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->registerUrl($instance));

        self::assertResponseIsSuccessful();
        $listNode = $crawler->filter('[data-widget-list]');
        self::assertCount(1, $listNode);
        self::assertSame('0', $listNode->attr('data-min'));
        self::assertSame('__w'.$listWidget->getId().'__', $listNode->attr('data-prototype-name'));
        self::assertStringContainsString('__w'.$listWidget->getId().'__', $listNode->attr('data-prototype'));
        self::assertCount(1, $crawler->filter('[data-widget-list-entry]'));
        self::assertCount(1, $crawler->filter('[data-widget-list-add]'));
        $entryName = $this->answerName($list).'[0]['.$member['name'].']';
        self::assertSelectorExists('input[name="'.$entryName.'"]');
        self::assertSelectorExists('input[type="email"][name="'.$this->answerName($list).'[0]['.$member['email'].']"]');
        self::assertSelectorTextContains('main', 'Coéquipiers');
    }

    public function testListWidgetAcceptsSeveralEntries(): void
    {
        $instance = $this->createInstance();
        [$form, $list, $member, $team] = $this->createListForm($instance, min: 1);
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->registerUrl($instance));
        $values = $crawler->selectButton('S\'inscrire')->form()->getPhpValues();
        $answers = &$values['event_registration_answer']['answers'];
        $answers[$team] = 'Les Ofnis';
        // indexes as left by the JS after adding 3 entries and removing the second one
        $answers[$list] = [
            0 => [$member['name'] => 'Alice', $member['email'] => 'alice@example.com'],
            2 => [$member['name'] => 'Bob', $member['email'] => ''],
            3 => [$member['name'] => '', $member['email'] => ''],
        ];
        $this->client->request('POST', $this->registerUrl($instance), $values);

        self::assertResponseRedirects($this->showUrl($instance));
        $registrations = $this->registrationsFor($form);
        self::assertCount(1, $registrations);
        // blank entries are dropped, the others renumbered
        self::assertSame([
            $team => 'Les Ofnis',
            $list => [
                [$member['name'] => 'Alice', $member['email'] => 'alice@example.com'],
                [$member['name'] => 'Bob'],
            ],
        ], $registrations[0]->getAnswers());

        // the admin table shows one line per entry
        $this->client->loginUser($this->admin);
        $this->client->request('GET', $this->listUrl($instance));
        self::assertSelectorTextContains('table thead', 'Coéquipiers');
        self::assertSelectorTextContains('table tbody', 'Nom : Alice ; Email : alice@example.com');
        self::assertSelectorTextContains('table tbody', 'Nom : Bob');
    }

    /**
     * @dataProvider emptyListProvider
     */
    public function testEmptyListDependsOnMinimum(int $min, int $expectedStatus): void
    {
        $instance = $this->createInstance();
        [$form, $list, , $team] = $this->createListForm($instance, min: $min);
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->registerUrl($instance));
        $values = $crawler->selectButton('S\'inscrire')->form()->getPhpValues();
        $values['event_registration_answer']['answers'][$team] = 'Les Ofnis';
        // the user removed the default entry
        unset($values['event_registration_answer']['answers'][$list]);
        $this->client->request('POST', $this->registerUrl($instance), $values);

        self::assertResponseStatusCodeSame($expectedStatus);
        self::assertCount(302 === $expectedStatus ? 1 : 0, $this->registrationsFor($form));
        if (422 === $expectedStatus) {
            self::assertSelectorTextContains('main', 'Ajoutez au moins une entrée');
        }
    }

    public static function emptyListProvider(): iterable
    {
        yield 'may be empty' => [0, 302];
        yield 'at least one entry' => [1, 422];
    }

    public function testListEntriesAreValidated(): void
    {
        $instance = $this->createInstance();
        [$form, $list, $member, $team] = $this->createListForm($instance, min: 0);
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->registerUrl($instance));
        $values = $crawler->selectButton('S\'inscrire')->form()->getPhpValues();
        $values['event_registration_answer']['answers'][$team] = 'Les Ofnis';
        $values['event_registration_answer']['answers'][$list] = [
            // a filled entry still needs its required name, and a valid email
            [$member['name'] => '', $member['email'] => 'not-an-email'],
        ];
        $this->client->request('POST', $this->registerUrl($instance), $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Ce champ est obligatoire');
        self::assertSelectorTextContains('main', 'Adresse email invalide');
        self::assertCount(0, $this->registrationsFor($form));
    }

    public function testListOfNativeWidget(): void
    {
        $instance = $this->createInstance();
        $listWidget = $this->createWidget('Allergies', FormWidgetKind::LIST, ['widget' => $this->natives()->get(FormWidget::TYPE_TEXT)->getId(), 'min' => 0]);
        $list = 'allergies';
        $form = $this->createForm($instance, widget: $this->createComposed('Repas '.$this->suffix, [[$listWidget, $list, 'Allergies']]));
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->registerUrl($instance));
        // optional list: its entries' inputs aren't required in the browser
        self::assertSelectorExists('input[name="'.$this->answerName($list).'[0]"]:not([required])');
        $values = $crawler->selectButton('S\'inscrire')->form()->getPhpValues();
        $values['event_registration_answer']['answers'][$list] = ['Arachides', '', 'Gluten'];
        $this->client->request('POST', $this->registerUrl($instance), $values);

        self::assertResponseRedirects();
        self::assertSame([$list => ['Arachides', 'Gluten']], $this->registrationsFor($form)[0]->getAnswers());
        self::assertEmailHtmlBodyContains($this->getMailerMessages()[0], 'Arachides<br />');
    }

    // ---------------------------------------------------------------------
    // Public: cancelling
    // ---------------------------------------------------------------------

    public function testUserCanCancelRegistration(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->createRegistration($form, $this->user);
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->showUrl($instance));
        $this->client->submit($crawler->filter('form[action="'.$this->cancelUrl($instance).'"]')->form());

        self::assertResponseRedirects($this->showUrl($instance));
        self::assertCount(0, $this->registrationsFor($form));
    }

    public function testCancelRequiresValidCsrfToken(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->createRegistration($form, $this->user);
        $this->client->loginUser($this->user);

        $this->client->request('POST', $this->cancelUrl($instance), ['_token' => 'invalid']);

        self::assertCount(1, $this->registrationsFor($form));
    }

    public function testCancelIsRefusedOnceClosed(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->createRegistration($form, $this->user);
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->showUrl($instance));
        $cancelForm = $crawler->filter('form[action="'.$this->cancelUrl($instance).'"]')->form();

        $this->setOpen($form, false);
        $this->client->submit($cancelForm);

        self::assertCount(1, $this->registrationsFor($form));
    }

    public function testCancelOnlyRemovesOwnRegistration(): void
    {
        $other = $this->createUser('other', []);
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->createRegistration($form, $this->user);
        $this->createRegistration($form, $other);
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', $this->showUrl($instance));
        $this->client->submit($crawler->filter('form[action="'.$this->cancelUrl($instance).'"]')->form());

        $remaining = $this->registrationsFor($form);
        self::assertCount(1, $remaining);
        self::assertSame($other->getId(), $remaining[0]->getUser()->getId());
    }

    // ---------------------------------------------------------------------
    // Admin: access control
    // ---------------------------------------------------------------------

    public function testNonAdminIsForbiddenOnAdminRoutes(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $registration = $this->createRegistration($form, $this->user);
        $this->client->loginUser($this->user);

        $id = $instance->getId();
        $requests = [
            ['GET', "/admin/events/instance/$id/registration"],
            ['POST', "/admin/events/instance/$id/registration/delete"],
            ['GET', "/admin/events/instance/$id/registrations"],
            ['GET', "/admin/events/instance/$id/registrations.csv"],
            ['POST', '/admin/registrations/'.$registration->getId().'/delete'],
        ];
        foreach ($requests as [$method, $url]) {
            $this->client->request($method, $url);
            self::assertResponseStatusCodeSame(403, "$method $url");
        }
    }

    // ---------------------------------------------------------------------
    // Admin: form builder
    // ---------------------------------------------------------------------

    public function testHeaderShowsAdminLinkOnlyToAdmins(): void
    {
        $adminLink = '#navbar a[href="/admin"]';

        $this->client->request('GET', '/');
        self::assertSelectorNotExists($adminLink);

        $this->client->loginUser($this->user);
        $this->client->request('GET', '/');
        self::assertSelectorNotExists($adminLink);

        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/');
        self::assertSelectorExists($adminLink);

        // Highlighted on admin pages
        $this->client->request('GET', '/admin/events');
        self::assertSelectorExists($adminLink.'.active');
    }

    public function testAdminEventsPageLinksToRegistrationPages(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->createRegistration($form, $this->user);
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/events');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/events/instance/'.$instance->getId().'/registration"]');
        self::assertSelectorExists('a[href="/admin/events/instance/'.$instance->getId().'/registrations"]');
    }

    public function testAdminCanCreateForm(): void
    {
        $instance = $this->createInstance();
        $this->client->loginUser($this->admin);

        $root = $this->createRootWidget();

        $crawler = $this->client->request('GET', $this->builderUrl($instance));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['event_registration_form[description]'] = 'Inscrivez-vous !';
        $form['event_registration_form[status]']->select('until');
        $form['event_registration_form[closesAt]'] = '2099-01-01T12:00';
        $form['event_registration_form[widget]']->select((string) $root->getId());
        $this->client->submit($form);

        self::assertResponseRedirects();

        $form = $this->formFor($instance);
        self::assertNotNull($form);
        self::assertSame('Inscrivez-vous !', $form->getDescription());
        self::assertTrue($form->isOpen());
        self::assertEquals(new \DateTime('2099-01-01 12:00'), $form->getClosesAt());
        self::assertSame($root->getId(), $form->getWidget()->getId());
    }

    public function testAdminFormNeedsWidget(): void
    {
        $instance = $this->createInstance();
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', $this->builderUrl($instance));
        $this->client->submit($crawler->selectButton('Enregistrer')->form());

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->formFor($instance));
    }

    public function testAdminWidgetChoicesAreComposedWidgetsOnly(): void
    {
        $instance = $this->createInstance();
        $root = $this->createRootWidget();
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', $this->builderUrl($instance));

        self::assertCount(1, $crawler->filter('select[name="event_registration_form[widget]"] option[value="'.$root->getId().'"]'));
        foreach ($this->natives()->all() as $native) {
            self::assertCount(0, $crawler->filter('select[name="event_registration_form[widget]"] option[value="'.$native->getId().'"]'));
        }
    }

    public function testAdminPagePreviewsSavedWidget(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->builderUrl($instance));

        self::assertSelectorTextContains('.registration-preview', 'Régime alimentaire');
        self::assertSelectorExists('.registration-preview select option[value="Vegan"]');
        self::assertSelectorExists('a[href="/admin/widgets/'.$form->getWidget()->getId().'/edit"]');
    }

    /**
     * @dataProvider statusProvider
     */
    public function testAdminStatusDrivesOpenAndDeadline(string $status, ?string $closesAt, bool $expectedOpen, ?string $expectedClosesAt): void
    {
        $instance = $this->createInstance();
        // Start from the opposite state so every assertion proves a change
        $this->createForm($instance, open: !$expectedOpen, closesAt: $expectedClosesAt ? null : new \DateTime('2099-06-01 10:00'));
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', $this->builderUrl($instance));
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['event_registration_form[status]']->select($status);
        $form['event_registration_form[closesAt]'] = $closesAt ?? '';
        $this->client->submit($form);

        self::assertResponseRedirects();
        $saved = $this->formFor($instance);
        self::assertSame($expectedOpen, $saved->isOpen());
        self::assertEquals($expectedClosesAt ? new \DateTime($expectedClosesAt) : null, $saved->getClosesAt());
    }

    public static function statusProvider(): iterable
    {
        yield 'closed' => ['closed', null, false, null];
        yield 'closed ignores a leftover date' => ['closed', '2099-01-01T12:00', false, null];
        yield 'open' => ['open', null, true, null];
        yield 'open ignores a leftover date' => ['open', '2099-01-01T12:00', true, null];
        yield 'open until' => ['until', '2099-01-01T12:00', true, '2099-01-01 12:00'];
    }

    public function testAdminUntilStatusRequiresDate(): void
    {
        $instance = $this->createInstance();
        $this->createForm($instance, open: false);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', $this->builderUrl($instance));
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['event_registration_form[status]']->select('until');
        $form['event_registration_form[closesAt]'] = '';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Indiquez la date de clôture');
        self::assertFalse($this->formFor($instance)->isOpen());
    }

    /**
     * @dataProvider prefilledStatusProvider
     */
    public function testAdminStatusIsPrefilled(bool $open, ?string $closesAt, string $expectedStatus): void
    {
        $instance = $this->createInstance();
        $this->createForm($instance, open: $open, closesAt: $closesAt ? new \DateTime($closesAt) : null);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', $this->builderUrl($instance));

        self::assertSame($expectedStatus, $crawler->selectButton('Enregistrer')->form()['event_registration_form[status]']->getValue());
    }

    public static function prefilledStatusProvider(): iterable
    {
        yield 'closed' => [false, null, 'closed'];
        yield 'open' => [true, null, 'open'];
        yield 'open until' => [true, '+1 month', 'until'];
        yield 'past deadline stays "until"' => [true, '-1 day', 'until'];
    }

    public function testAdminNewFormIsClosedByDefault(): void
    {
        $instance = $this->createInstance();
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', $this->builderUrl($instance));

        self::assertSame('closed', $crawler->selectButton('Enregistrer')->form()['event_registration_form[status]']->getValue());
    }

    /**
     * @dataProvider statusLineProvider
     */
    public function testStatusLineShowsCurrentState(bool $open, ?string $closesAt, string $expected): void
    {
        $instance = $this->createInstance();
        $this->createForm($instance, open: $open, closesAt: $closesAt ? new \DateTime($closesAt) : null);
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->builderUrl($instance));
        self::assertSelectorTextContains('.registration-status', $expected);

        $this->client->request('GET', $this->listUrl($instance));
        self::assertSelectorTextContains('.registration-status', $expected);
    }

    public static function statusLineProvider(): iterable
    {
        yield 'closed' => [false, null, 'Actuellement : inscriptions fermées'];
        yield 'open' => [true, null, 'Actuellement : inscriptions ouvertes'];
        yield 'open until' => [true, '2099-01-01 12:00', 'Actuellement : inscriptions ouvertes jusqu\'au 01/01/2099 à 12:00'];
        yield 'past deadline' => [true, '2001-01-01 12:00', 'Actuellement : inscriptions fermées (date de clôture dépassée le 01/01/2001 à 12:00)'];
    }

    public function testStatusLineOnNewForm(): void
    {
        $instance = $this->createInstance();
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->builderUrl($instance));

        self::assertSelectorTextContains('.registration-status', 'Pas encore de formulaire');
    }

    public function testAdminCanDeleteForm(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->createRegistration($form, $this->user);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', $this->builderUrl($instance));
        $url = '/admin/events/instance/'.$instance->getId().'/registration/delete';
        $this->client->submit($crawler->filter('form[action="'.$url.'"]')->form());

        self::assertResponseRedirects();
        self::assertNull($this->formFor($instance));
        self::assertCount(0, $this->em()->getRepository(Registration::class)->findBy(['user' => $this->user->getId()]));
    }

    public function testAdminDeleteFormRequiresCsrf(): void
    {
        $instance = $this->createInstance();
        $this->createForm($instance);
        $this->client->loginUser($this->admin);

        $this->client->request('POST', '/admin/events/instance/'.$instance->getId().'/registration/delete', ['_token' => 'invalid']);

        self::assertNotNull($this->formFor($instance));
    }

    public function testDeletingInstanceDeletesFormAndRegistrations(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->createRegistration($form, $this->user);
        $formId = $form->getId();
        $widgetId = $form->getWidget()->getId();
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/events/instance/'.$instance->getId().'/delete');

        self::assertResponseRedirects();
        $em = $this->em();
        self::assertNull($em->find(RegistrationForm::class, $formId));
        self::assertCount(0, $em->getRepository(Registration::class)->findBy(['form' => $formId]));
        // the widget stays in the library
        self::assertNotNull($em->find(FormWidget::class, $widgetId));
    }

    // ---------------------------------------------------------------------
    // Admin: registrations
    // ---------------------------------------------------------------------

    public function testAdminRegistrationListShowsAnswers(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->createRegistration($form, $this->user, 'Les Ofnis', 'Vegan');
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->listUrl($instance));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table thead', 'Régime alimentaire');
        self::assertSelectorTextContains('table tbody', $this->user->getEmail());
        self::assertSelectorTextContains('table tbody', 'Les Ofnis');
        self::assertSelectorTextContains('table tbody', 'Vegan');
        self::assertSelectorExists('a[href="'.$this->listUrl($instance).'.csv"]');
    }

    public function testAdminCsvExport(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $this->createRegistration($form, $this->user, 'Les "Ofnis"; vrai', 'Vegan');
        $this->client->loginUser($this->admin);

        ob_start();
        $this->client->request('GET', $this->listUrl($instance).'.csv');
        $content = ob_get_clean() ?: $this->client->getInternalResponse()->getContent();

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('text/csv', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringStartsWith("\u{FEFF}", $content);

        $rows = array_map(
            fn (string $line) => str_getcsv($line, ';', '"', ''),
            array_values(array_filter(explode("\n", substr($content, 3))))
        );
        self::assertCount(2, $rows);
        self::assertSame(['Nom', 'Prénom', 'Pseudo', 'Email', 'Date', 'Nom de l\'équipe', 'Remarques', 'Régime alimentaire'], $rows[0]);
        self::assertSame($this->user->getName(), $rows[1][0]);
        self::assertSame($this->user->getEmail(), $rows[1][3]);
        self::assertSame('Les "Ofnis"; vrai', $rows[1][5]);
        self::assertSame('', $rows[1][6]);
        self::assertSame('Vegan', $rows[1][7]);
    }

    public function testAdminCanDeleteRegistration(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $registration = $this->createRegistration($form, $this->user);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', $this->listUrl($instance));
        $url = '/admin/registrations/'.$registration->getId().'/delete';
        $this->client->submit($crawler->filter('form[action="'.$url.'"]')->form());

        self::assertResponseRedirects($this->listUrl($instance));
        self::assertCount(0, $this->registrationsFor($form));
    }

    public function testAdminDeleteRegistrationRequiresCsrf(): void
    {
        $instance = $this->createInstance();
        $form = $this->createForm($instance);
        $registration = $this->createRegistration($form, $this->user);
        $this->client->loginUser($this->admin);

        $this->client->request('POST', '/admin/registrations/'.$registration->getId().'/delete', ['_token' => 'invalid']);

        self::assertCount(1, $this->registrationsFor($form));
    }

    // ---------------------------------------------------------------------
    // Fixtures & helpers
    // ---------------------------------------------------------------------

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    private function createUser(string $prefix, array $roles): User
    {
        $em = $this->em();
        $user = (new User())
            ->setEmail("$prefix.{$this->suffix}@edu.univ-fcomte.fr")
            ->setPseudo(substr("$prefix{$this->suffix}", 0, 20))
            ->setName('Nom'.$prefix)
            ->setFirstName('Prenom'.$prefix)
            ->setRoles($roles)
            ->setVerified(true);
        $user->setPassword('not-used');
        $em->persist($user);
        $em->flush();
        $this->userIds[] = $user->getId();

        return $user;
    }

    private function createInstance(): AssoEventInstance
    {
        $em = $this->em();
        $event = (new AssoEvent())
            ->setName('Test event '.$this->suffix)
            ->setSlug('test-event-'.$this->suffix)
            ->setDescription('Description')
            ->setDescriptionMini('Mini');
        $em->persist($event);

        $instance = (new AssoEventInstance())
            ->setParentEvent($event)
            ->setName('Test instance '.$this->suffix)
            ->setSlug('test-instance-'.$this->suffix)
            ->setDate(new \DateTime('+1 month'));
        $em->persist($instance);
        $em->flush();
        $this->eventIds[] = $event->getId();

        return $instance;
    }

    private function createWidget(string $name, FormWidgetKind $kind, array $content): FormWidget
    {
        $em = $this->em();
        $widget = (new FormWidget())->setName($name)->setKind($kind)->setContent($content);
        $em->persist($widget);
        $em->flush();
        $this->widgetIds[] = $widget->getId();

        return $widget;
    }

    private function natives(): NativeWidgets
    {
        return static::getContainer()->get(NativeWidgets::class);
    }

    /**
     * A composed widget of three natives (their keys kept in $this->fields): required text, optional textarea, optional choice.
     */
    private function createRootWidget(): FormWidget
    {
        $this->fields = ['team', 'notes', 'diet'];

        return $this->createComposed('Inscription '.$this->suffix, [
            [$this->natives()->get(FormWidget::TYPE_TEXT), 'team', 'Nom de l\'équipe', true],
            [$this->natives()->get(FormWidget::TYPE_TEXTAREA), 'notes', 'Remarques'],
            [$this->natives()->get(FormWidget::TYPE_CHOICE), 'diet', 'Régime alimentaire', false, ['Végétarien', 'Vegan', 'Sans restriction']],
        ]);
    }

    /**
     * @param array<array{0: FormWidget, 1: string, 2: string, 3?: bool, 4?: string[]}> $entries widget, key, name, required, choices
     */
    private function createComposed(string $name, array $entries): FormWidget
    {
        return $this->createWidget($name, FormWidgetKind::COMPOSED, array_map(
            fn (array $entry) => FormWidgetEntry::toContent($entry[0]->getId(), $entry[1], $entry[2], $entry[3] ?? false, $entry[4] ?? []),
            $entries
        ));
    }

    /**
     * A form made of a required "Équipe" text and a "Coéquipiers" list of
     * composed members (required name, optional email).
     *
     * @return array{0: RegistrationForm, 1: string, 2: array{name: string, email: string}, 3: string, 4: FormWidget}
     */
    private function createListForm(AssoEventInstance $instance, int $min): array
    {
        $member = ['name' => 'name', 'email' => 'email'];
        $memberWidget = $this->createComposed('Coéquipier '.$this->suffix, [
            [$this->natives()->get(FormWidget::TYPE_TEXT), 'name', 'Nom', true],
            [$this->natives()->get(FormWidget::TYPE_EMAIL), 'email', 'Email'],
        ]);
        $listWidget = $this->createWidget('Coéquipiers '.$this->suffix, FormWidgetKind::LIST, ['widget' => $memberWidget->getId(), 'min' => $min]);
        $form = $this->createForm($instance, widget: $this->createComposed('Inscription équipe '.$this->suffix, [
            [$this->natives()->get(FormWidget::TYPE_TEXT), 'team', 'Équipe', true],
            [$listWidget, 'members', 'Coéquipiers'],
        ]));

        return [$form, 'members', $member, 'team', $listWidget];
    }

    private function createForm(
        AssoEventInstance $instance,
        bool $open = true,
        ?\DateTimeInterface $closesAt = null,
        ?string $description = null,
        ?FormWidget $widget = null,
    ): RegistrationForm {
        // widgets first: their flush must not see the unpersisted form
        $widget ??= $this->createRootWidget();
        $em = $this->em();
        $form = (new RegistrationForm())
            ->setInstance($instance)
            ->setDescription($description)
            ->setOpen($open)
            ->setClosesAt($closesAt)
            ->setWidget($widget);

        $em->persist($form);
        $em->flush();

        return $form;
    }

    private function createRegistration(RegistrationForm $form, User $user, string $team = 'Équipe', ?string $diet = null, ?array $answers = null): Registration
    {
        $em = $this->em();
        if (null === $answers) {
            [$text, , $choice] = $this->fields;
            $answers = [$text => $team];
            if (null !== $diet) {
                $answers[$choice] = $diet;
            }
        }

        $registration = (new Registration())
            ->setForm($em->find(RegistrationForm::class, $form->getId()))
            ->setUser($em->find(User::class, $user->getId()))
            ->setAnswers($answers);
        $em->persist($registration);
        $em->flush();
        // The form's inverse "registrations" collection is stale in this entity manager,
        // which the first request reuses: start from what is in the database.
        $em->clear();

        return $registration;
    }

    private function setOpen(RegistrationForm $form, bool $open): void
    {
        $em = $this->em();
        $em->find(RegistrationForm::class, $form->getId())->setOpen($open);
        $em->flush();
    }

    private function formFor(AssoEventInstance $instance): ?RegistrationForm
    {
        $em = $this->em();
        $em->clear();

        return $em->getRepository(RegistrationForm::class)->findOneBy(['instance' => $instance->getId()]);
    }

    /** @return Registration[] */
    private function registrationsFor(RegistrationForm $form): array
    {
        $em = $this->em();
        $em->clear();

        return $em->getRepository(Registration::class)->findBy(['form' => $form->getId()]);
    }

    private function answerName(string $key): string
    {
        return 'event_registration_answer[answers]['.$key.']';
    }

    private function showUrl(AssoEventInstance $instance): string
    {
        return '/events/instance/'.$instance->getSlug().'-'.$instance->getId();
    }

    private function registerUrl(AssoEventInstance $instance): string
    {
        return $this->showUrl($instance).'/inscription';
    }

    private function cancelUrl(AssoEventInstance $instance): string
    {
        return '/events/instance/'.$instance->getId().'/inscription/annuler';
    }

    private function builderUrl(AssoEventInstance $instance): string
    {
        return '/admin/events/instance/'.$instance->getId().'/registration';
    }

    private function listUrl(AssoEventInstance $instance): string
    {
        return '/admin/events/instance/'.$instance->getId().'/registrations';
    }
}
