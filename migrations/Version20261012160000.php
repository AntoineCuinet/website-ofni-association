<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Native widgets become fixed (one per HTML input type), and the name users see
 * moves to the entries of composed widgets.
 */
final class Version20261012160000 extends AbstractMigration
{
    /** Same as App\Entity\FormWidget::TYPES */
    private const TYPES = [
        'Texte court' => 'text',
        'Texte long' => 'textarea',
        'Email' => 'email',
        'Nombre' => 'number',
        'Date' => 'date',
        'Case à cocher' => 'checkbox',
        'Liste déroulante' => 'choice',
        'Téléphone' => 'tel',
    ];

    public function getDescription(): string
    {
        return 'Fixed native form widgets; displayed names, required flags and options move to composed entries';
    }

    public function up(Schema $schema): void
    {
        $widgets = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, name, kind, content FROM form_widget ORDER BY id') as $row) {
            $row['content'] = json_decode($row['content'], true) ?: [];
            $widgets[(int) $row['id']] = $row;
        }

        // one native per type: the first existing one is kept (and renamed), the others merged into it
        $natives = [];
        $replacement = [];
        foreach ($widgets as $id => $widget) {
            if ('native' !== $widget['kind']) {
                continue;
            }
            $type = in_array($widget['content']['type'] ?? null, self::TYPES, true) ? $widget['content']['type'] : 'text';
            if (isset($natives[$type])) {
                $replacement[$id] = $natives[$type];
                $this->addSql('DELETE FROM form_widget WHERE id = ?', [$id]);
            } else {
                $natives[$type] = $id;
                $this->addSql('UPDATE form_widget SET name = ?, content = ? WHERE id = ?', [array_search($type, self::TYPES, true), json_encode(['type' => $type]), $id]);
            }
        }
        foreach (self::TYPES as $label => $type) {
            if (!isset($natives[$type])) {
                $this->addSql('INSERT INTO form_widget (name, kind, content) VALUES (?, ?, ?)', [$label, 'native', json_encode(['type' => $type])]);
            }
        }

        foreach ($widgets as $id => $widget) {
            if ('composed' === $widget['kind']) {
                // former entries were {widget: id}: the name, required flag and options came from the widget
                $entries = [];
                foreach ($widget['content'] as $entry) {
                    $childId = (int) ($entry['widget'] ?? 0);
                    $child = $widgets[$childId] ?? null;
                    if (null === $child) {
                        continue;
                    }
                    $entries[] = array_filter([
                        'widget' => $replacement[$childId] ?? $childId,
                        'key' => $entry['key'] ?? 'e'.bin2hex(random_bytes(4)),
                        'name' => $entry['name'] ?? $child['name'],
                        'required' => (bool) ($entry['required'] ?? $child['content']['required'] ?? false),
                        'choices' => $entry['choices'] ?? $child['content']['choices'] ?? null,
                    ], fn ($value) => null !== $value);
                }
                $this->addSql('UPDATE form_widget SET content = ? WHERE id = ?', [json_encode($entries), $id]);
            } elseif ('list' === $widget['kind'] && isset($widget['content']['widget'])) {
                $innerId = (int) $widget['content']['widget'];
                $content = ['widget' => $replacement[$innerId] ?? $innerId, 'min' => (int) ($widget['content']['min'] ?? 0)];
                if ($choices = $widget['content']['choices'] ?? $widgets[$innerId]['content']['choices'] ?? null) {
                    $content['choices'] = $choices;
                }
                $this->addSql('UPDATE form_widget SET content = ? WHERE id = ?', [json_encode($content), $id]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Composed entries can\'t be turned back into per-widget names.');
    }
}
