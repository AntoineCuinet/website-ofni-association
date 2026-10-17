<?php

namespace App\Model;

use App\Entity\FormWidget;

/**
 * One entry of a composed widget: a widget, with the name users see and its options.
 */
final class FormWidgetEntry
{
    /**
     * @param string   $key     stable answer key, kept when entries are renamed or reordered
     * @param string[] $choices options of a dropdown
     */
    public function __construct(
        public readonly FormWidget $widget,
        public readonly string $key,
        public readonly string $name,
        public readonly bool $required = false,
        public readonly array $choices = [],
    ) {
    }

    public static function newKey(): string
    {
        // prefixed so it never becomes an integer array key
        return 'e'.bin2hex(random_bytes(4));
    }

    /**
     * Raw entry as stored in the composed widget's content.
     */
    public static function toContent(int $widgetId, string $key, string $name, bool $required, array $choices): array
    {
        return array_filter([
            'widget' => $widgetId,
            'key' => $key,
            'name' => $name,
            'required' => $required,
            'choices' => $choices ?: null,
        ], fn ($value) => null !== $value);
    }
}
