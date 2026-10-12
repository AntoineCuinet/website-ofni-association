<?php

namespace App\Tests;

use App\Entity\AssoEvent;
use App\Entity\AssoEventInstance;
use App\Entity\FormWidget;
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
 * Functional tests for the admin widget library (/admin/widgets).
 */
class FormWidgetControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $suffix;

    /** @var int[] */
    private array $userIds = [];
    /** @var int[] */
    private array $eventIds = [];

    private User $admin;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->suffix = bin2hex(random_bytes(4));
        $this->admin = $this->createUser('admin', ['ROLE_ADMIN']);
    }

    protected function tearDown(): void
    {
        try {
            $connection = $this->em()->getConnection();
            $delete = fn (string $sql, array $ids) => $ids && $connection->executeStatement($sql, [$ids], [ArrayParameterType::INTEGER]);
            $delete('DELETE FROM asso_event_instance WHERE parent_event_id IN (?)', $this->eventIds);
            $delete('DELETE FROM asso_event WHERE id IN (?)', $this->eventIds);
            $delete('DELETE FROM user WHERE id IN (?)', $this->userIds);
            // widgets created here or through the UI all carry the suffix
            $connection->executeStatement('DELETE FROM form_widget WHERE name LIKE ?', ['%'.$this->suffix.'%']);
        } finally {
            parent::tearDown();
        }
    }

    public function testNonAdminIsForbidden(): void
    {
        $widget = $this->createComposed('Form', [[FormWidget::TYPE_TEXT, 'a', 'A']]);
        $this->client->loginUser($this->createUser('user', []));

        $id = $widget->getId();
        foreach ([['GET', '/admin/widgets'], ['GET', '/admin/widgets/new'], ['GET', "/admin/widgets/$id"], ['GET', "/admin/widgets/$id/edit"], ['POST', "/admin/widgets/$id/delete"]] as [$method, $url]) {
            $this->client->request($method, $url);
            self::assertResponseStatusCodeSame(403, "$method $url");
        }
    }

    public function testIndexListsWidgetsNativesAndUsages(): void
    {
        $root = $this->createComposed('Inscription', [[FormWidget::TYPE_TEXT, 'pseudo', 'Pseudo']]);
        $instance = $this->createInstanceWithForm($root);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin/widgets');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table tbody', $root->getName());
        self::assertSelectorTextContains('table tbody', 'Inscription : '.$instance->getName());
        // natives: listed, but neither editable nor deletable
        foreach ($this->natives()->all() as $native) {
            self::assertSelectorTextContains('.widget-natives', $native->getName());
            self::assertCount(0, $crawler->filter('a[href="/admin/widgets/'.$native->getId().'/edit"]'));
            self::assertCount(0, $crawler->filter('form[action="/admin/widgets/'.$native->getId().'/delete"]'));
        }
    }

    public function testAdminDashboardLinksToLibrary(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin');

        self::assertSelectorExists('a[href="/admin/widgets"]');
    }

    public function testNativesAreFixed(): void
    {
        $native = $this->natives()->get(FormWidget::TYPE_TEXT);
        $this->client->loginUser($this->admin);

        // no native kind to create
        $crawler = $this->client->request('GET', '/admin/widgets/new?kind=native');
        self::assertSame('composed', $crawler->selectButton('Enregistrer')->form()['form_widget_admin[kind]']->getValue());
        self::assertCount(0, $crawler->filter('select[name="form_widget_admin[kind]"] option[value="native"]'));

        $this->client->request('GET', '/admin/widgets/'.$native->getId().'/edit');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/admin/widgets/'.$native->getId().'/delete');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'champs de base');
        self::assertNotNull($this->em()->find(FormWidget::class, $native->getId()));
    }

    public function testCreateComposedWidget(): void
    {
        $text = $this->natives()->get(FormWidget::TYPE_TEXT);
        $choice = $this->natives()->get(FormWidget::TYPE_CHOICE);
        $member = $this->createComposed('Coéquipier', [[FormWidget::TYPE_TEXT, 'name', 'Nom']]);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin/widgets/new?kind=composed');
        self::assertSelectorExists('[data-entry-list][data-prototype]');
        // natives and library widgets can be picked, with their native type for the JS
        self::assertSelectorExists('[data-entry-list]');
        $prototype = $crawler->filter('[data-entry-list]')->attr('data-prototype');
        self::assertStringContainsString('data-native-type="choice"', $prototype);
        self::assertStringContainsString($member->getName(), $prototype);

        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['form_widget_admin']['name'] = 'Form '.$this->suffix;
        $values['form_widget_admin']['entries'] = [
            // the same native twice
            ['widget' => (string) $text->getId(), 'name' => ' Prénom ', 'required' => '1', 'choices' => 'ignored'],
            ['widget' => (string) $text->getId(), 'name' => 'Nom'],
            ['widget' => (string) $choice->getId(), 'name' => 'Taille', 'choices' => "S\r\n M \r\n\r\nL"],
            // "required" only applies to inputs
            ['widget' => (string) $member->getId(), 'name' => 'Capitaine', 'required' => '1'],
            // an empty row is ignored
            ['widget' => '', 'name' => ''],
        ];
        $this->client->request('POST', '/admin/widgets/new?kind=composed', $values);

        self::assertResponseRedirects();
        $content = $this->findByName('Form '.$this->suffix)->getContent();
        self::assertCount(4, $content);
        foreach ($content as $entry) {
            self::assertMatchesRegularExpression('/^e[0-9a-f]{8}$/', $entry['key']);
        }
        self::assertCount(4, array_unique(array_column($content, 'key')));
        $withoutKeys = array_map(fn (array $entry) => array_diff_key($entry, ['key' => true]), $content);
        self::assertSame([
            ['widget' => $text->getId(), 'name' => 'Prénom', 'required' => true],
            ['widget' => $text->getId(), 'name' => 'Nom', 'required' => false],
            ['widget' => $choice->getId(), 'name' => 'Taille', 'required' => false, 'choices' => ['S', 'M', 'L']],
            ['widget' => $member->getId(), 'name' => 'Capitaine', 'required' => false],
        ], $withoutKeys);
    }

    /**
     * @dataProvider invalidEntryProvider
     */
    public function testInvalidEntryIsRejected(array $entry, string $message): void
    {
        $this->client->loginUser($this->admin);
        $entry['widget'] = (string) $this->natives()->get($entry['widget'])->getId();

        $crawler = $this->client->request('GET', '/admin/widgets/new?kind=composed');
        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['form_widget_admin']['name'] = 'Form '.$this->suffix;
        $values['form_widget_admin']['entries'] = [$entry];
        $this->client->request('POST', '/admin/widgets/new?kind=composed', $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', $message);
        self::assertNull($this->findByName('Form '.$this->suffix));
    }

    public static function invalidEntryProvider(): iterable
    {
        yield 'no name' => [['widget' => FormWidget::TYPE_TEXT, 'name' => ' '], 'le nom affiché est obligatoire'];
        yield 'dropdown without options' => [['widget' => FormWidget::TYPE_CHOICE, 'name' => 'Taille', 'choices' => " \n "], 'au moins une option'];
    }

    public function testComposedNeedsAnEntry(): void
    {
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin/widgets/new?kind=composed');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['form_widget_admin[name]'] = 'Form '.$this->suffix;
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'au moins un widget');
    }

    public function testCreateListWidget(): void
    {
        $member = $this->createComposed('Coéquipier', [[FormWidget::TYPE_TEXT, 'name', 'Nom']]);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin/widgets/new?kind=list');
        $form = $crawler->selectButton('Enregistrer')->form();
        self::assertSame('list', $form['form_widget_admin[kind]']->getValue());
        $form['form_widget_admin[name]'] = 'Coéquipiers '.$this->suffix;
        $form['form_widget_admin[listWidget]']->select((string) $member->getId());
        $form['form_widget_admin[atLeastOne]']->tick();
        // options only apply to a list of dropdowns
        $form['form_widget_admin[listChoices]'] = 'ignored';
        $this->client->submit($form);

        self::assertResponseRedirects();
        self::assertSame(['widget' => $member->getId(), 'min' => 1], $this->findByName('Coéquipiers '.$this->suffix)->getContent());
    }

    public function testListOfDropdownsNeedsOptions(): void
    {
        $choice = $this->natives()->get(FormWidget::TYPE_CHOICE);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin/widgets/new?kind=list');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['form_widget_admin[name]'] = 'Tailles '.$this->suffix;
        $form['form_widget_admin[listWidget]']->select((string) $choice->getId());
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'au moins une option');

        $form['form_widget_admin[listChoices]'] = "S\nM";
        $this->client->submit($form);
        self::assertResponseRedirects();
        self::assertSame(['widget' => $choice->getId(), 'min' => 0, 'choices' => ['S', 'M']], $this->findByName('Tailles '.$this->suffix)->getContent());
    }

    public function testEditKeepsAnswerKeysAndKindIsLocked(): void
    {
        $composed = $this->createComposed('Form', [[FormWidget::TYPE_TEXT, 'first', 'Prénom'], [FormWidget::TYPE_EMAIL, 'mail', 'Email']]);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin/widgets/'.$composed->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="form_widget_admin[kind]"][disabled]');
        // the widget itself isn't offered
        self::assertCount(0, $crawler->filter('select[name="form_widget_admin[entries][0][widget]"] option[value="'.$composed->getId().'"]'));
        // preview of the saved widget, with the entries' names
        self::assertSelectorExists('.registration-preview input[name="widget_composed[first]"]');
        self::assertSelectorTextContains('.registration-preview', 'Prénom');

        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $entries = $values['form_widget_admin']['entries'];
        self::assertSame(['first', 'mail'], array_column($entries, 'key'));
        self::assertSame('Prénom', $entries[0]['name']);

        // email moved first and renamed, as renumbered by the JS; kind change ignored
        $values['form_widget_admin']['kind'] = 'list';
        $values['form_widget_admin']['entries'] = [array_merge($entries[1], ['name' => 'Courriel']), $entries[0]];
        $this->client->request('POST', '/admin/widgets/'.$composed->getId().'/edit', $values);

        self::assertResponseRedirects();
        $saved = $this->findByName($composed->getName());
        self::assertSame(FormWidgetKind::COMPOSED, $saved->getKind());
        self::assertSame(['mail', 'first'], array_column($saved->getContent(), 'key'));
        self::assertSame(['Courriel', 'Prénom'], array_column($saved->getContent(), 'name'));
    }

    public function testCycleIsRejected(): void
    {
        $inner = $this->createComposed('Inner', [[FormWidget::TYPE_TEXT, 'a', 'A']]);
        $outer = $this->createWidget('Outer', FormWidgetKind::COMPOSED, [['widget' => $inner->getId(), 'key' => 'inner', 'name' => 'Inner']]);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin/widgets/'.$inner->getId().'/edit');
        $values = $crawler->selectButton('Enregistrer')->form()->getPhpValues();
        $values['form_widget_admin']['entries'][] = ['widget' => (string) $outer->getId(), 'name' => 'Outer'];
        $this->client->request('POST', '/admin/widgets/'.$inner->getId().'/edit', $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'ne peut pas se contenir lui-même');
        self::assertCount(1, $this->findByName($inner->getName())->getContent());
    }

    public function testEditWarnsAboutForms(): void
    {
        $member = $this->createComposed('Membre', [[FormWidget::TYPE_TEXT, 'a', 'A']]);
        $root = $this->createWidget('Inscription', FormWidgetKind::COMPOSED, [['widget' => $member->getId(), 'key' => 'm', 'name' => 'Membre']]);
        $instance = $this->createInstanceWithForm($root);
        $this->client->loginUser($this->admin);

        // used through the root widget
        $this->client->request('GET', '/admin/widgets/'.$member->getId().'/edit');

        self::assertSelectorTextContains('main', 'utilisé par 1 formulaire(s)');
        self::assertSelectorTextContains('main', $instance->getName());
    }

    public function testPreviewRendersWidget(): void
    {
        $member = $this->createComposed('Coéquipier', [[FormWidget::TYPE_TEXT, 'name', 'Nom']]);
        $list = $this->createWidget('Coéquipiers', FormWidgetKind::LIST, ['widget' => $member->getId(), 'min' => 1]);
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/widgets/'.$list->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-widget-list][data-min="1"] [data-widget-list-entry] input[name="widget_composed[widget][0][name]"]');
        self::assertSelectorTextContains('.registration-preview', 'au moins une entrée');
    }

    public function testDeleteUnusedWidget(): void
    {
        $widget = $this->createComposed('Form', [[FormWidget::TYPE_TEXT, 'a', 'A']]);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin/widgets');
        $this->client->submit($crawler->filter('form[action="/admin/widgets/'.$widget->getId().'/delete"]')->form());

        self::assertResponseRedirects('/admin/widgets');
        self::assertNull($this->findByName($widget->getName()));
    }

    public function testDeleteIsRefusedWhileUsed(): void
    {
        $member = $this->createComposed('Membre', [[FormWidget::TYPE_TEXT, 'a', 'A']]);
        $root = $this->createWidget('Inscription', FormWidgetKind::COMPOSED, [['widget' => $member->getId(), 'key' => 'm', 'name' => 'Membre']]);
        $this->createInstanceWithForm($root);
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin/widgets');
        foreach ([$member, $root] as $widget) {
            $this->client->submit($crawler->filter('form[action="/admin/widgets/'.$widget->getId().'/delete"]')->form());
            $this->client->followRedirect();
            self::assertSelectorTextContains('.alert-danger', 'est utilisé');
            self::assertNotNull($this->findByName($widget->getName()));
        }
    }

    public function testDeleteRequiresCsrf(): void
    {
        $widget = $this->createComposed('Form', [[FormWidget::TYPE_TEXT, 'a', 'A']]);
        $this->client->loginUser($this->admin);

        $this->client->request('POST', '/admin/widgets/'.$widget->getId().'/delete', ['_token' => 'invalid']);

        self::assertNotNull($this->findByName($widget->getName()));
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
        $user = (new User())
            ->setEmail("$prefix.{$this->suffix}@edu.univ-fcomte.fr")
            ->setPseudo(substr("w$prefix{$this->suffix}", 0, 20))
            ->setName('Nom'.$prefix)
            ->setFirstName('Prenom'.$prefix)
            ->setRoles($roles)
            ->setVerified(true);
        $user->setPassword('not-used');
        $this->em()->persist($user);
        $this->em()->flush();
        $this->userIds[] = $user->getId();

        return $user;
    }

    private function createWidget(string $name, FormWidgetKind $kind, array $content): FormWidget
    {
        $widget = (new FormWidget())->setName($name.' '.$this->suffix)->setKind($kind)->setContent($content);
        $this->em()->persist($widget);
        $this->em()->flush();

        return $widget;
    }

    /**
     * @param array<array{0: string, 1: string, 2: string}> $entries native type, key, name
     */
    private function createComposed(string $name, array $entries): FormWidget
    {
        return $this->createWidget($name, FormWidgetKind::COMPOSED, array_map(
            fn (array $entry) => FormWidgetEntry::toContent($this->natives()->get($entry[0])->getId(), $entry[1], $entry[2], false, []),
            $entries
        ));
    }

    private function natives(): NativeWidgets
    {
        return static::getContainer()->get(NativeWidgets::class);
    }

    private function createInstanceWithForm(FormWidget $widget): AssoEventInstance
    {
        $em = $this->em();
        $event = (new AssoEvent())
            ->setName('Widget event '.$this->suffix)
            ->setSlug('widget-event-'.$this->suffix)
            ->setDescription('Description')
            ->setDescriptionMini('Mini');
        $em->persist($event);
        $instance = (new AssoEventInstance())
            ->setParentEvent($event)
            ->setName('Widget instance '.$this->suffix)
            ->setSlug('widget-instance-'.$this->suffix)
            ->setDate(new \DateTime('+1 month'));
        $em->persist($instance);
        $em->persist((new RegistrationForm())->setInstance($instance)->setWidget($widget)->setOpen(true));
        $em->flush();
        $this->eventIds[] = $event->getId();
        $em->clear();

        return $instance;
    }

    private function findByName(string $name): ?FormWidget
    {
        $this->em()->clear();

        return $this->em()->getRepository(FormWidget::class)->findOneBy(['name' => $name]);
    }
}
