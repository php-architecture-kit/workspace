<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\LazyOperators\Application;

/**
 * Starting points for the builder page, written in the ExpressionBuilder definition format.
 */
final class ExpressionPresets
{
    /**
     * @return list<array{name: string, description: string, definition: array<string, mixed>, inputs: array<string, array<string, mixed>>}>
     */
    public function all(): array
    {
        $now = self::node('cast_datetime', ['value' => self::port('now'), 'timezone' => self::string('Europe/Warsaw')]);
        $arrival = self::node('datetime_add', ['datetime' => $now, 'interval' => self::node('interval_literal', ['value' => 'PT30M'])]);
        $done = self::node('datetime_add', ['datetime' => $arrival, 'interval' => self::node('interval_literal', ['value' => 'PT45M'])]);
        $issued = self::node('cast_datetime', ['value' => self::port('issued'), 'timezone' => self::string('Europe/Warsaw')]);
        $promoStart = self::node('datetime_literal', ['value' => '2026-11-27T00:00:00+01:00', 'timezone' => 'Europe/Warsaw']);

        $subtotal = self::node('multiplication', [
            'left' => self::node('cast_float', ['value' => self::port('price')]),
            'right' => self::node('cast_integer', ['value' => self::port('quantity')]),
        ]);

        return [
            [
                'name' => 'Order total with discount',
                'description' => 'price × quantity, 10% off above 100, rounded to 2 places.',
                'definition' => self::node('math_round', [
                    'value' => self::node('cast_float', [
                        'value' => self::node('if_else', [
                            'condition' => self::node('greater_than', ['left' => $subtotal, 'right' => self::float(100.0)]),
                            'then' => self::node('multiplication', ['left' => $subtotal, 'right' => self::float(0.9)]),
                            'else' => $subtotal,
                        ]),
                    ]),
                    'precision' => self::int(2),
                ]),
                'inputs' => [
                    'price' => self::float(24.99),
                    'quantity' => self::int(5),
                ],
            ],
            [
                'name' => 'Unit price',
                'description' => 'total ÷ quantity; fails with a message when quantity is not positive.',
                'definition' => self::node('if_else', [
                    'condition' => self::node('greater_than', ['left' => self::port('quantity'), 'right' => self::int(0)]),
                    'then' => self::node('division', [
                        'left' => self::node('cast_float', ['value' => self::port('total')]),
                        'right' => self::node('cast_float', ['value' => self::port('quantity')]),
                    ]),
                    'else' => self::node('fail', ['message' => self::string('quantity must be positive')]),
                ]),
                'inputs' => [
                    'total' => self::float(124.95),
                    'quantity' => self::int(0),
                ],
            ],
            [
                'name' => 'Split an invoice',
                'description' => 'Allocate an amount 50/30/20, remainder to the largest share.',
                'definition' => self::node('allocation', [
                    'amount' => self::node('cast_float', ['value' => self::port('amount')]),
                    'precision' => self::int(2),
                    'remainderTarget' => 'Largest',
                    'firstShare' => self::int(50),
                    'restShares' => [self::int(30), self::int(20)],
                ]),
                'inputs' => [
                    'amount' => self::float(1000.01),
                ],
            ],
            [
                'name' => 'Shop open?',
                'description' => 'Open Mon–Sat 09:00–17:00 in Warsaw; 30 min to get there, 45 min of shopping. ⟨now⟩ may be in any offset.',
                'definition' => self::node('if_else', [
                    'condition' => self::node('and', [
                        'left' => self::node('and', [
                            'left' => self::node('less_than_or_equal', [
                                'left' => self::node('datetime_part', ['datetime' => $arrival, 'part' => 'DayOfWeek']),
                                'right' => self::int(6),
                            ]),
                            'right' => self::node('time_of_day_between', [
                                'datetime' => $arrival,
                                'from' => self::node('time_literal', ['value' => '09:00']),
                                'to' => self::node('time_literal', ['value' => '17:00']),
                            ]),
                        ]),
                        'right' => self::node('less_than_or_equal', [
                            'left' => self::node('datetime_part', ['datetime' => $done, 'part' => 'SecondOfDay']),
                            'right' => self::int(17 * 3600),
                        ]),
                    ]),
                    'then' => self::string('Go shopping'),
                    'else' => self::node('fail', ['message' => self::string('The shop is open Mon–Sat 09:00–17:00 Europe/Warsaw')]),
                ]),
                'inputs' => [
                    'now' => self::string('2026-10-17T14:00:00Z'),
                ],
            ],
            [
                'name' => 'Valid for 7 business days',
                'description' => 'Valid from issuing until the end of the 7th business day after it, skipping the holidays in ⟨holidays⟩.',
                'definition' => self::node('range_contains', [
                    'range' => self::node('datetime_range', [
                        'start' => $issued,
                        'end' => self::node('start_of', [
                            'datetime' => self::node('datetime_add', [
                                'datetime' => self::node('business_days_add', [
                                    'datetime' => $issued,
                                    'days' => self::int(7),
                                    'holidays' => self::node('cast_array', ['value' => self::port('holidays')]),
                                ]),
                                'interval' => self::node('interval_literal', ['value' => 'P1D']),
                            ]),
                            'unit' => 'Day',
                        ]),
                    ]),
                    'datetime' => self::node('cast_datetime', ['value' => self::port('now'), 'timezone' => self::string('Europe/Warsaw')]),
                ]),
                'inputs' => [
                    'issued' => self::string('2026-12-23T14:00:00+01:00'),
                    'holidays' => self::node('array_literal', ['value' => ['2026-12-24', '2026-12-25', '2026-12-26', '2027-01-01', '2027-01-06']]),
                    'now' => self::string('2027-01-07T20:00:00Z'),
                ],
            ],
            [
                'name' => 'Promo window',
                'description' => 'A 3-day promotion from midnight 27 November in Warsaw: is ⟨now⟩ inside it?',
                'definition' => self::node('range_contains', [
                    'range' => self::node('datetime_range', [
                        'start' => $promoStart,
                        'end' => self::node('datetime_add', ['datetime' => $promoStart, 'interval' => self::node('interval_literal', ['value' => 'P3D'])]),
                    ]),
                    'datetime' => self::node('cast_datetime', ['value' => self::port('now'), 'timezone' => self::string('Europe/Warsaw')]),
                ]),
                'inputs' => [
                    'now' => self::string('2026-11-29T22:59:59Z'),
                ],
            ],
            [
                'name' => 'Shipping label',
                'description' => 'Switch on a zone code, with a default.',
                'definition' => self::node('switch_case', [
                    'condition' => self::port('zone'),
                    'cases' => [
                        ['condition' => self::string('EU'), 'value' => self::string('Standard')],
                        ['condition' => self::string('US'), 'value' => self::string('International')],
                    ],
                    'default' => self::string('Unsupported zone'),
                ]),
                'inputs' => [
                    'zone' => self::string('EU'),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private static function node(string $expression, array $arguments): array
    {
        return ['expression' => $expression, 'arguments' => $arguments];
    }

    /**
     * @return array<string, mixed>
     */
    private static function port(string $name): array
    {
        return self::node('runtime_port', ['name' => $name]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function int(int $value): array
    {
        return self::node('int_literal', ['value' => $value]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function float(float $value): array
    {
        return self::node('float_literal', ['value' => $value]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function string(string $value): array
    {
        return self::node('string_literal', ['value' => $value]);
    }
}
