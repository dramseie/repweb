<?php
// src/Frw/Service/PricingService.php
namespace App\Frw\Service;

use Doctrine\DBAL\Connection;
use App\Frw\Domain\Pricing\PricerRegistry;

final class PricingService
{
    public function __construct(private Connection $db, private PricerRegistry $reg) {}

    public function priceRun(int $runId): array
    {
        $run = $this->db->fetchAssociative('SELECT * FROM frw_run WHERE id=?', [$runId]);
        if (!$run) throw new \RuntimeException('Run not found');
        $tpl = $this->db->fetchAssociative('SELECT * FROM frw_template WHERE id=?', [$run['template_id']]);
        $schema = json_decode($tpl['schema_json'], true) ?? [];
        $answers = json_decode($run['answers_json'], true) ?? [];

        $lines = [];
        $ctx = [];

        foreach (($schema['steps'] ?? []) as $step) {
            foreach (($step['pricing'] ?? []) as $rule) {
                $catalog = $this->db->fetchAssociative('SELECT * FROM frw_catalog_item WHERE code=?', [$rule['catalog']]);
                if (!$catalog) continue;
                $cat = $catalog; $cat['formula_json'] = is_string($cat['formula_json']) ? json_decode($cat['formula_json'], true) : $cat['formula_json'];

                $qty = $this->evalSimple($rule['qty'] ?? 1, $answers, null);
                $args = $this->mapArgs($rule['args'] ?? [], $answers, null);
                $type = $cat['formula_json']['type'] ?? 'per_unit';

                $priced = $this->reg->get($type)->price([
                    'qty' => $qty,
                    'args' => $args,
                    'answers' => $answers,
                    'item' => null,
                    'catalog' => $cat,
                    'ctx' => $ctx,
                ]);
                $lines[] = array_merge(['sku'=>$catalog['code']], $priced);
            }

            // handle repeats at step-level
            if (isset($step['repeat'])) {
                $count = (int)$this->evalSimple($step['repeat']['countFrom'] ?? 0, $answers, null);
                for ($i=0; $i<$count; $i++) {
                    foreach (($step['repeat']['pricing'] ?? []) as $rule) {
                        $catalog = $this->db->fetchAssociative('SELECT * FROM frw_catalog_item WHERE code=?', [$rule['catalog']]);
                        if (!$catalog) continue;
                        $cat = $catalog; $cat['formula_json'] = is_string($cat['formula_json']) ? json_decode($cat['formula_json'], true) : $cat['formula_json'];
                        $item = $answers['vms'][$i] ?? ($answers['vm_'.$i] ?? []);
                        $qty = $this->evalSimple($rule['qty'] ?? 1, $answers, $item);
                        $args = $this->mapArgs($rule['args'] ?? [], $answers, $item);
                        $type = $cat['formula_json']['type'] ?? 'per_unit';
                        $priced = $this->reg->get($type)->price([
                            'qty' => $qty,
                            'args' => $args,
                            'answers' => $answers,
                            'item' => $item,
                            'catalog' => $cat,
                            'ctx' => $ctx,
                        ]);
                        $lines[] = array_merge(['sku'=>$catalog['code']], $priced, ['meta'=>['vmIndex'=>$i] + ($priced['meta'] ?? [])]);
                    }
                }
            }
        }

        $total = array_sum(array_map(fn($l)=> (int)$l['extended_cents'], $lines));
        $this->db->update('frw_run', [
            'pricing_breakdown_json' => json_encode($lines),
            'total_cents' => $total,
        ], ['id'=>$runId]);

        $run['pricing_breakdown_json'] = json_encode($lines);
        $run['total_cents'] = $total;
        return $run;
    }

    private function evalSimple($expr, array $answers, ?array $item)
    {
        if (is_numeric($expr)) return $expr + 0;
        if (is_string($expr)) {
            // very small evaluator: dot-paths like 'answers.vm_count' or 'item.cpu'
            if (str_starts_with($expr, 'answers.')) return $this->getPath($answers, substr($expr, 8));
            if (str_starts_with($expr, 'item.') && $item) return $this->getPath($item, substr($expr, 5));
            if (preg_match('/^len\(([^)]+)\)$/', $expr, $m)) {
                $arr = $this->evalSimple($m[1], $answers, $item);
                return is_array($arr) ? count($arr) : 0;
            }
        }
        return 0;
    }

    private function mapArgs(array $args, array $answers, ?array $item): array
    {
        $out = [];
        foreach ($args as $k=>$v) {
            $out[$k] = $this->evalSimple($v, $answers, $item);
        }
        return $out;
    }

    private function getPath($src, string $path)
    {
        $cur = $src; foreach (explode('.', $path) as $seg) { if (!is_array($cur) || !array_key_exists($seg, $cur)) return null; $cur = $cur[$seg]; }
        return $cur;
    }
}