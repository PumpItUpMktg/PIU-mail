<?php

namespace App\Parcels;

/**
 * Counts for one page of an import. Counts and dates only: no owner data.
 */
final class PageStats
{
    /** @var array<string, mixed> */
    public array $data = [
        'fetched' => 0,
        'inserted' => 0,
        'updated' => 0,
        'duplicates' => 0,
        'skipped_by_class' => 0,
        'null_year' => 0,
        'null_geometry' => 0,
        'bldg_desc_present' => 0,
        'bldg_desc_decoded' => 0,
        'counts_by_class' => [],
        'skipped_classes' => [],
        'pub_min' => null, 'pub_max' => null,
        'upd_min' => null, 'upd_max' => null,
    ];

    public function observe(MappedParcel $p): void
    {
        $class = $p->columns['prop_class'] ?? '(none)';
        $this->data['counts_by_class'][$class] = ($this->data['counts_by_class'][$class] ?? 0) + 1;

        if ($p->columns['yr_constr'] === null) {
            $this->data['null_year']++;
        }
        if ($p->geometryJson === null) {
            $this->data['null_geometry']++;
        }
        if ($p->bldgDescPresent) {
            $this->data['bldg_desc_present']++;
        }
        if ($p->bldgDescDecoded) {
            $this->data['bldg_desc_decoded']++;
        }

        $this->range('pub', $p->columns['pcl_pub_date']);
        $this->range('upd', $p->columns['pcl_last_upd']);
    }

    public function skip(MappedParcel $p): void
    {
        $class = $p->columns['prop_class'] ?? '(none)';
        $this->data['skipped_by_class']++;
        $this->data['skipped_classes'][$class] = ($this->data['skipped_classes'][$class] ?? 0) + 1;
    }

    /**
     * Sum a list of page stats into report totals.
     *
     * @param  iterable<array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    public static function sum(iterable $pages): array
    {
        $total = (new self)->data;
        foreach ($pages as $page) {
            foreach ($page as $k => $v) {
                if (in_array($k, ['counts_by_class', 'skipped_classes'], true)) {
                    foreach ((array) $v as $class => $n) {
                        $total[$k][$class] = ($total[$k][$class] ?? 0) + $n;
                    }
                } elseif (str_ends_with($k, '_min')) {
                    $total[$k] = self::min($total[$k], $v);
                } elseif (str_ends_with($k, '_max')) {
                    $total[$k] = self::max($total[$k], $v);
                } elseif (is_int($v)) {
                    $total[$k] = ($total[$k] ?? 0) + $v;
                }
            }
        }
        ksort($total['counts_by_class']);
        ksort($total['skipped_classes']);

        return $total;
    }

    private function range(string $prefix, ?string $date): void
    {
        if ($date === null) {
            return;
        }
        $this->data[$prefix.'_min'] = self::min($this->data[$prefix.'_min'], $date);
        $this->data[$prefix.'_max'] = self::max($this->data[$prefix.'_max'], $date);
    }

    private static function min(?string $a, ?string $b): ?string
    {
        return $a === null ? $b : ($b === null ? $a : min($a, $b));
    }

    private static function max(?string $a, ?string $b): ?string
    {
        return $a === null ? $b : ($b === null ? $a : max($a, $b));
    }
}
