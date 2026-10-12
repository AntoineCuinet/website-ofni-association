<?php

namespace App\Tests\Entity;

use App\Entity\RegistrationForm;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
}
