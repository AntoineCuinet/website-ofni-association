<?php

namespace App\Tests\Entity;

use App\Entity\FormWidget;
use App\Enum\FormWidgetKind;
use App\Model\FormWidgetEntry;
use App\Service\FormWidgetAnswerFormatter;
use App\Service\NativeWidgets;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FormWidgetTest extends KernelTestCase
{
    /** @var FormWidget[] */
    private array $created = [];

    protected function tearDown(): void
    {
        $em = $this->em();
        foreach (array_reverse($this->created) as $widget) {
            $em->remove($em->find(FormWidget::class, $widget->getId()));
        }
        $em->flush();
        parent::tearDown();
    }

    public function testNativesAreFixedAndComplete(): void
    {
        $natives = $this->natives()->all();

        self::assertSame(array_values(FormWidget::TYPES), array_keys($natives));
        foreach ($natives as $type => $native) {
            self::assertTrue($native->isNative());
            self::assertSame(['type' => $type], $native->getContent());
            self::assertSame(array_search($type, FormWidget::TYPES, true), $native->getName());
            self::assertCount(0, $this->validate($native));
        }
        // created once
        self::assertSame($natives[FormWidget::TYPE_TEXT]->getId(), $this->natives()->get(FormWidget::TYPE_TEXT)->getId());
    }

    public function testUnknownNativeTypeIsInvalid(): void
    {
        self::assertCount(1, $this->validate($this->widget(FormWidgetKind::NATIVE, ['type' => 'color'])));
    }

    public function testNameIsRequired(): void
    {
        $widget = $this->widget(FormWidgetKind::COMPOSED, [$this->entry(FormWidget::TYPE_TEXT, 'a', 'A')])->setName('');

        self::assertCount(1, $this->validate($widget));
    }

    /**
     * @dataProvider listProvider
     */
    public function testListContent(?string $innerType, int $min, array $choices, int $expectedViolations): void
    {
        $content = ['min' => $min, 'choices' => $choices] + ($innerType ? ['widget' => $this->natives()->get($innerType)->getId()] : []);

        self::assertCount($expectedViolations, $this->validate($this->widget(FormWidgetKind::LIST, $content)));
    }

    public static function listProvider(): iterable
    {
        yield 'may be empty' => [FormWidget::TYPE_TEXT, 0, [], 0];
        yield 'at least one' => [FormWidget::TYPE_TEXT, 1, [], 0];
        yield 'no inner widget' => [null, 0, [], 1];
        yield 'min above one' => [FormWidget::TYPE_TEXT, 2, [], 1];
        yield 'dropdown with options' => [FormWidget::TYPE_CHOICE, 0, ['A'], 0];
        yield 'dropdown without options' => [FormWidget::TYPE_CHOICE, 0, [' '], 1];
    }

    public function testListOfMissingWidgetIsInvalid(): void
    {
        self::assertCount(1, $this->validate($this->widget(FormWidgetKind::LIST, ['widget' => 999999, 'min' => 0])));
    }

    public function testComposedEntries(): void
    {
        // the same native twice, under two names
        $composed = $this->widget(FormWidgetKind::COMPOSED, [
            $this->entry(FormWidget::TYPE_TEXT, 'first', 'Prénom'),
            $this->entry(FormWidget::TYPE_TEXT, 'last', 'Nom'),
            $this->entry(FormWidget::TYPE_CHOICE, 'size', 'Taille', ['S', 'M']),
        ]);
        self::assertCount(0, $this->validate($composed));
        $text = $this->natives()->get(FormWidget::TYPE_TEXT)->getId();
        self::assertSame([$text, $text, $this->natives()->get(FormWidget::TYPE_CHOICE)->getId()], $composed->getChildIds());
    }

    /**
     * @dataProvider invalidEntriesProvider
     */
    public function testInvalidComposedEntries(array $entries): void
    {
        $content = array_map(fn (array $entry) => null === $entry[0]
            ? ['widget' => null, 'key' => $entry[1], 'name' => $entry[2]]
            : $this->entry(...$entry), $entries);

        self::assertCount(1, $this->validate($this->widget(FormWidgetKind::COMPOSED, $content)));
    }

    public static function invalidEntriesProvider(): iterable
    {
        yield 'no entry' => [[]];
        yield 'no widget' => [[[null, 'a', 'A']]];
        yield 'no name' => [[[FormWidget::TYPE_TEXT, 'a', ' ']]];
        yield 'dropdown without options' => [[[FormWidget::TYPE_CHOICE, 'a', 'A', []]]];
        yield 'duplicate key' => [[[FormWidget::TYPE_TEXT, 'a', 'A'], [FormWidget::TYPE_EMAIL, 'a', 'B']]];
        yield 'invalid key' => [[[FormWidget::TYPE_TEXT, '1a', 'A']]];
    }

    public function testWidgetCannotContainItself(): void
    {
        $outer = $this->persisted(FormWidgetKind::COMPOSED, [$this->entry(FormWidget::TYPE_TEXT, 'a', 'A')]);
        $list = $this->persisted(FormWidgetKind::LIST, ['widget' => $outer->getId(), 'min' => 0]);

        // directly
        $outer->setContent([['widget' => $outer->getId(), 'key' => 'self', 'name' => 'Moi']]);
        self::assertCount(1, $this->validate($outer));

        // through a list: outer > list > outer
        $outer->setContent([$this->entry(FormWidget::TYPE_TEXT, 'a', 'A'), ['widget' => $list->getId(), 'key' => 'l', 'name' => 'Liste']]);
        self::assertCount(1, $this->validate($outer));
    }

    public function testFormatterFlattensNestedAnswers(): void
    {
        $member = $this->persisted(FormWidgetKind::COMPOSED, [
            $this->entry(FormWidget::TYPE_TEXT, 'name', 'Nom'),
            $this->entry(FormWidget::TYPE_EMAIL, 'email', 'Email'),
        ]);
        $list = $this->persisted(FormWidgetKind::LIST, ['widget' => $member->getId(), 'min' => 0]);
        $formatter = static::getContainer()->get(FormWidgetAnswerFormatter::class);

        self::assertSame("Nom : Alice ; Email : a@b.fr\nNom : Bob", $formatter->format($list, [
            ['name' => 'Alice', 'email' => 'a@b.fr'],
            ['name' => 'Bob'],
        ]));
        self::assertSame('', $formatter->format($list, null));
        self::assertSame('x', $formatter->format($this->natives()->get(FormWidget::TYPE_TEXT), 'x'));
        self::assertSame('Non', $formatter->format($this->natives()->get(FormWidget::TYPE_CHECKBOX), null));
        self::assertSame('31/12/2099', $formatter->format($this->natives()->get(FormWidget::TYPE_DATE), '2099-12-31'));
    }

    private function entry(string $nativeType, string $key, string $name, array $choices = []): array
    {
        return FormWidgetEntry::toContent($this->natives()->get($nativeType)->getId(), $key, $name, false, $choices);
    }

    private function natives(): NativeWidgets
    {
        return static::getContainer()->get(NativeWidgets::class);
    }

    private function widget(FormWidgetKind $kind, array $content, string $name = 'Widget'): FormWidget
    {
        return (new FormWidget())->setName($name)->setKind($kind)->setContent($content);
    }

    private function persisted(FormWidgetKind $kind, array $content, string $name = 'Widget'): FormWidget
    {
        $widget = $this->widget($kind, $content, $name);
        $this->em()->persist($widget);
        $this->em()->flush();
        $this->created[] = $widget;

        return $widget;
    }

    private function validate(FormWidget $widget): iterable
    {
        return static::getContainer()->get(ValidatorInterface::class)->validate($widget);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }
}
