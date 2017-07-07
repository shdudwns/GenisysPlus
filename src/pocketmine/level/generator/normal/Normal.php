<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

namespace pocketmine\level\generator\normal;

use pocketmine\block\Block;
use pocketmine\block\CoalOre;
use pocketmine\block\DiamondOre;
use pocketmine\block\Dirt;
use pocketmine\block\GoldOre;
use pocketmine\block\Gravel;
use pocketmine\block\IronOre;
use pocketmine\block\LapisOre;
use pocketmine\block\RedstoneOre;
use pocketmine\block\Stone;
use pocketmine\level\ChunkManager;
use pocketmine\level\generator\biome\Biome;
use pocketmine\level\generator\biome\BiomeSelector;
use pocketmine\level\generator\Generator;
use pocketmine\level\generator\noise\Simplex;
use pocketmine\level\generator\normal\object\OreType;
use pocketmine\level\generator\normal\populator\Cave;
use pocketmine\level\generator\normal\populator\GroundCover;
use pocketmine\level\generator\normal\populator\Ore;
use pocketmine\level\generator\populator\Populator;
use pocketmine\level\Level;
use pocketmine\math\Vector3 as Vector3;
use pocketmine\utils\Random;

class Normal extends Generator{

	/** @var Populator[] */
	protected $populators = [];
	/** @var ChunkManager */
	protected $level;
	/** @var Random */
	protected $random;
	protected $waterHeight = 62;
	protected $bedrockDepth = 5;

	/** @var Populator[] */
	protected $generationPopulators = [];
	/** @var Simplex */
	protected $noiseBase;

	/** @var BiomeSelector */
	protected $selector;

	private static $GAUSSIAN_KERNEL = null;
	private static $SMOOTH_SIZE = 2;

	public function __construct(array $options = []){
		if(self::$GAUSSIAN_KERNEL === null){
			self::generateKernel();
		}
	}

	private static function generateKernel(){
		self::$GAUSSIAN_KERNEL = [];

		$bellSize = 1 / self::$SMOOTH_SIZE;
		$bellHeight = 2 * self::$SMOOTH_SIZE;

		for($sx = -self::$SMOOTH_SIZE; $sx <= self::$SMOOTH_SIZE; ++$sx){
			self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE] = [];

			for($sz = -self::$SMOOTH_SIZE; $sz <= self::$SMOOTH_SIZE; ++$sz){
				$bx = $bellSize * $sx;
				$bz = $bellSize * $sz;
				self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE][$sz + self::$SMOOTH_SIZE] = $bellHeight * exp(-($bx * $bx + $bz * $bz) / 2);
			}
		}
	}

	public function getName(){
		return "Normal";
	}

	public function getWaterHeight(): int{
        return $this->waterHeight;
    }

    public function getSettings(){
		return [];
	}

	public function pickBiome($x, $z){
		$hash = $x * 2345803 ^ $z * 9236449 ^ $this->level->getSeed();
		$hash *= $hash + 223;
		$xNoise = $hash >> 20 & 3;
		$zNoise = $hash >> 22 & 3;
		if($xNoise == 3){
			$xNoise = 1;
		}
		if($zNoise == 3){
			$zNoise = 1;
		}

		return $this->selector->pickBiome($x + $xNoise - 1, $z + $zNoise - 1);
	}

	public function init(ChunkManager $level, Random $random){
		$this->level = $level;
		$this->random = $random;
		$this->random->setSeed($this->level->getSeed());
		$this->noiseBase = new Simplex($this->random, 4, 1 / 4, 1 / 32);
		$this->random->setSeed($this->level->getSeed());
		$this->selector = new BiomeSelector($this->random, Biome::getBiome(Biome::OCEAN));

		$this->selector->addBiome(Biome::getBiome(Biome::OCEAN));
		$this->selector->addBiome(Biome::getBiome(Biome::PLAINS));
		$this->selector->addBiome(Biome::getBiome(Biome::DESERT));
		$this->selector->addBiome(Biome::getBiome(Biome::MOUNTAINS));
		$this->selector->addBiome(Biome::getBiome(Biome::FOREST));
		$this->selector->addBiome(Biome::getBiome(Biome::TAIGA));
		$this->selector->addBiome(Biome::getBiome(Biome::SWAMP));
		$this->selector->addBiome(Biome::getBiome(Biome::RIVER));
		$this->selector->addBiome(Biome::getBiome(Biome::ICE_PLAINS));
		$this->selector->addBiome(Biome::getBiome(Biome::SMALL_MOUNTAINS));
		$this->selector->addBiome(Biome::getBiome(Biome::BIRCH_FOREST));
		$this->selector->addBiome(Biome::getBiome(Biome::BEACH));
		$this->selector->addBiome(Biome::getBiome(Biome::MESA));

		$this->selector->recalculate();

		$cover = new GroundCover();
		$this->generationPopulators[] = $cover;

		$cave = new Cave();
		$this->populators[] = $cave;

		$ores = new Ore();
		$ores->setOreTypes([
			new OreType(new CoalOre(), 20, 17, 0, 128),
			new OreType(new IronOre(), 20, 9, 0, 64),
			new OreType(new RedstoneOre(), 8, 8, 0, 16),
			new OreType(new LapisOre(), 1, 7, 0, 16),
			new OreType(new GoldOre(), 2, 9, 0, 32),
			new OreType(new DiamondOre(), 1, 8, 0, 16),
			new OreType(new Dirt(), 10, 33, 0, 128),
            new OreType(new Stone(Stone::GRANITE), 10, 33, 0, 80),
            new OreType(new Stone(Stone::DIORITE), 10, 33, 0, 80),
            new OreType(new Stone(Stone::ANDESITE), 10, 33, 0, 80),
			new OreType(new Gravel(), 8, 33, 0, 128)
		]);
		$this->populators[] = $ores;
	}

	public function generateChunk($chunkX, $chunkZ){
		$this->random->setSeed(0xdeadbeef ^ $chunkX ^ $chunkZ ^ $this->level->getSeed());

		$noise = Generator::getFastNoise3D($this->noiseBase, 16, 128, 16, 4, 8, 4, $chunkX * 16, 0, $chunkZ * 16);

		$chunk = $this->level->getChunk($chunkX, $chunkZ);

		$biomeCache = [];

		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$minSum = 0;
				$maxSum = 0;
				$weightSum = 0;

				$biome = $this->pickBiome($chunkX * 16 + $x, $chunkZ * 16 + $z);
				$chunk->setBiomeId($x, $z, $biome->getId());

				for($sx = -self::$SMOOTH_SIZE; $sx <= self::$SMOOTH_SIZE; ++$sx){
					for($sz = -self::$SMOOTH_SIZE; $sz <= self::$SMOOTH_SIZE; ++$sz){

						$weight = self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE][$sz + self::$SMOOTH_SIZE];

						if($sx === 0 and $sz === 0){
							$adjacent = $biome;
						}else{
							$index = Level::chunkHash($chunkX * 16 + $x + $sx, $chunkZ * 16 + $z + $sz);
							if(isset($biomeCache[$index])){
								$adjacent = $biomeCache[$index];
							}else{
								$biomeCache[$index] = $adjacent = $this->pickBiome($chunkX * 16 + $x + $sx, $chunkZ * 16 + $z + $sz);
							}
						}

						$minSum += ($adjacent->getMinElevation() - 1) * $weight;
						$maxSum += $adjacent->getMaxElevation() * $weight;

						$weightSum += $weight;
					}
				}

				$minSum /= $weightSum;
				$maxSum /= $weightSum;

				$solidLand = false;

                for($y = 127; $y >= 0; --$y){
					if($y === 0){
						$chunk->setBlockId($x, $y, $z, Block::BEDROCK);
						continue;
					}

                    $noiseAdjustment = 2 * (($maxSum - $y) / ($maxSum - $minSum)) - 1;

                    $caveLevel = $minSum - 10;
                    $distAboveCaveLevel = max(0, $y - $caveLevel);

                    $noiseAdjustment = min($noiseAdjustment, 0.4 + ($distAboveCaveLevel / 10));
                    $noiseValue = $noise[$x][$z][$y] + $noiseAdjustment;

					if($noiseValue > 0){
						$chunk->setBlockId($x, $y, $z, Block::STONE);
					}elseif($y <= $this->waterHeight && $solidLand == false){
						$chunk->setBlockId($x, $y, $z, Block::STILL_WATER);
					}
				}
			}
		}

		foreach($this->generationPopulators as $populator){
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}
	}

	public function populateChunk($chunkX, $chunkZ){
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 16) ^ ($chunkZ << 16) ^ $this->level->getSeed());
		foreach($this->populators as $populator){
			$populator->populate($this->level, ($chunkX << 16), ($chunkZ << 16), $this->random);
		}

		$chunk = $this->level->getChunk($chunkX, $chunkZ);
		$biome = Biome::getBiome($chunk->getBiomeId(7, 7)); // This is incorrect. Here need add one mt_rand with all biomes and delete temperature & rainfall method.
		$biome->populateChunk($this->level, $chunkX, $chunkZ, $this->random);
	}

	public function getSpawn(){
		return new Vector3(127.5, 128, 127.5);
	}

}