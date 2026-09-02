<?php

declare(strict_types=1);

use Bga\Games\Goblins\BoardConfig;
use Bga\Games\Goblins\LandType;
use Bga\Games\Goblins\NationTile;
use Bga\Games\Goblins\Square;
use Bga\Games\Goblins\TileLayout;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../modules/php/constants.inc.php';
require_once __DIR__ . '/../modules/php/LandType.php';
require_once __DIR__ . '/../modules/php/NationTile.php';
require_once __DIR__ . '/../modules/php/BoardConfig.php';
require_once __DIR__ . '/../modules/php/TileLayout.php';

class TileLayoutTest extends TestCase {

    public function testDwarfCannotBePlacedOnEmptySquareWithoutFairy(): void {
        $grid = new BoardConfig(
            1,
            'test',
            1,
            1,
            [new Square(LandType::NONE)],
            []
        );

        $dwarf = new NationTile(['type' => Constants::NATION_DWARF], []);

        Assert::assertFalse(TileLayout::canPlaceTile($dwarf, 0, $grid, [], false));
    }

    public function testFairyAllowsDwarfOnEmptySquare(): void {
        $grid = new BoardConfig(
            1,
            'test',
            1,
            1,
            [new Square(LandType::NONE)],
            []
        );

        $dwarf = new NationTile(['type' => Constants::NATION_DWARF], []);

        Assert::assertTrue(TileLayout::canPlaceTile($dwarf, 0, $grid, [], true));
    }
}
