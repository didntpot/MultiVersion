<?php

namespace MultiVersion\network\proto\latest;


use MultiVersion\network\proto\static\MVBlockStateDictionary;
use MultiVersion\network\proto\static\MVBlockTranslator;
use MultiVersion\network\proto\static\MVItemIdMetaDowngrader;
use MultiVersion\network\proto\static\MVItemTranslator;
use MultiVersion\network\proto\static\MVTypeConverter;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\network\mcpe\convert\ItemTypeDictionaryFromDataHelper;
use pocketmine\network\mcpe\convert\LegacySkinAdapter;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;

class LatestTypeConverter{
	use SingletonTrait;

	private MVTypeConverter $typeConverter;

	public function __construct(){
		$this->typeConverter = new MVTypeConverter(
			$blockItemIdMap = BlockItemIdMap::getInstance(),
			$blockTranslator = new MVBlockTranslator(
				MVBlockStateDictionary::loadFromString(Filesystem::fileGetContents(BedrockDataFiles::CANONICAL_BLOCK_STATES_NBT), Filesystem::fileGetContents(BedrockDataFiles::BLOCK_STATE_META_MAP_JSON)),
				GlobalBlockStateHandlers::getSerializer(),
			),
			$itemTypeDictionary = ItemTypeDictionaryFromDataHelper::loadFromString(Filesystem::fileGetContents(BedrockDataFiles::REQUIRED_ITEM_LIST_JSON)),
			new MVItemTranslator(
				$itemTypeDictionary,
				$blockTranslator->getBlockStateDictionary(),
				GlobalItemDataHandlers::getSerializer(),
				GlobalItemDataHandlers::getDeserializer(),
				$blockItemIdMap,
				new MVItemIdMetaDowngrader($itemTypeDictionary, 121)
			),
			new LegacySkinAdapter(),
		);
	}

	public function getTypeConverter() : MVTypeConverter{
		return $this->typeConverter;
	}
}