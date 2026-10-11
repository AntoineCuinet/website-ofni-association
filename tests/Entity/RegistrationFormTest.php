<?php

namespace App\Tests\Entity;

use App\Entity\RegistrationField;
use App\Entity\RegistrationForm;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationFormTest extends KernelTestCase
{
    /**
     * @dataProvider acceptingProvider
     */
    public function testIsAcceptingRegistrations(bool $open, ?string $closesAt, bool $expected): void
    {
        $form = (new RegistrationForm())
            ->setOpen($open)
            ->setClosesAt(null === $closesAt ? null : new \DateTime($closesAt));

        self::assertSame($expected, $form->isAcceptingRegistrations());
    }

    public static function acceptingProvider(): iterable
    {
        yield 'open, no deadline' => [true, null, true];
        yield 'open, future deadline' => [true, '+1 day', true];
        yield 'open, past deadline' => [true, '-1 minute', false];
        yield 'closed, no deadline' => [false, null, false];
        yield 'closed, future deadline' => [false, '+1 day', false];
    }

    public function testNewFormIsClosedByDefault(): void
    {
        self::assertFalse((new RegistrationForm())->isOpen());
    }

    public function testAddAndRemoveFieldKeepsBothSidesInSync(): void
    {
        $form = new RegistrationForm();
        $field = new RegistrationField();

        $form->addField($field);
        $form->addField($field);
        self::assertCount(1, $form->getFields());
        self::assertSame($form, $field->getForm());

        $form->removeField($field);
        self::assertCount(0, $form->getFields());
    }

    public function testChoiceFieldWithoutChoicesIsInvalid(): void
    {
        $field = (new RegistrationField())
            ->setLabel('Taille')
            ->setType(RegistrationField::TYPE_CHOICE)
            ->setChoices([]);

        self::assertGreaterThan(0, count($this->validator()->validate($field)));
    }

    public function testChoiceFieldWithChoicesIsValid(): void
    {
        $field = (new RegistrationField())
            ->setLabel('Taille')
            ->setType(RegistrationField::TYPE_CHOICE)
            ->setChoices(['S', 'M']);

        self::assertCount(0, $this->validator()->validate($field));
    }

    public function testTextFieldDoesNotNeedChoices(): void
    {
        $field = (new RegistrationField())
            ->setLabel('Équipe')
            ->setType(RegistrationField::TYPE_TEXT);

        self::assertCount(0, $this->validator()->validate($field));
    }

    public function testUnknownTypeIsInvalid(): void
    {
        $field = (new RegistrationField())
            ->setLabel('Équipe')
            ->setType('file');

        self::assertGreaterThan(0, count($this->validator()->validate($field)));
    }

    public function testTypesListsEveryType(): void
    {
        self::assertEqualsCanonicalizing(
            [RegistrationField::TYPE_TEXT, RegistrationField::TYPE_TEXTAREA, RegistrationField::TYPE_CHOICE],
            array_values(RegistrationField::TYPES)
        );
    }

    private function validator(): ValidatorInterface
    {
        self::bootKernel();

        return static::getContainer()->get(ValidatorInterface::class);
    }
}
