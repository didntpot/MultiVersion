<?php

namespace MultiVersion\network\proto\v486;

use JsonException;
use MultiVersion\Loader;
use MultiVersion\network\proto\static\MVBlockStateDictionary;
use MultiVersion\network\proto\static\MVBlockTranslator;
use MultiVersion\network\proto\static\MVItemIdMetaDowngrader;
use MultiVersion\network\proto\static\MVItemTranslator;
use MultiVersion\network\proto\static\MVTypeConverter;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\network\mcpe\convert\ItemTypeDictionaryFromDataHelper;
use pocketmine\network\mcpe\convert\LegacySkinAdapter;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use Symfony\Component\Filesystem\Path;

class v486TypeConverter{
	use SingletonTrait;

	private MVTypeConverter $typeConverter;

	/**
	 * @throws JsonException
	 */
	public function __construct(){
		$this->typeConverter = new MVTypeConverter(
			$blockItemIdMap = BlockItemIdMap::getInstance(),
			$blockTranslator = new MVBlockTranslator(
				MVBlockStateDictionary::loadFromString(Filesystem::fileGetContents(Path::join(Loader::getResourcesPath(), "v486", "canonical_block_states.nbt")), Filesystem::fileGetContents(Path::join(Loader::getResourcesPath(), "v486", "block_state_meta_map.json"))),
				GlobalBlockStateHandlers::getSerializer(),
			),
			$itemTypeDictionary = ItemTypeDictionaryFromDataHelper::loadFromString(Filesystem::fileGetContents(Path::join(Loader::getResourcesPath(), "v486", "required_item_list.json"))),
			new MVItemTranslator(
				$itemTypeDictionary,
				$blockTranslator->getBlockStateDictionary(),
				GlobalItemDataHandlers::getSerializer(),
				GlobalItemDataHandlers::getDeserializer(),
				$blockItemIdMap,
				new MVItemIdMetaDowngrader($itemTypeDictionary, 61)
			),
			new LegacySkinAdapter(),
		);
	}

	public function getTypeConverter() : MVTypeConverter{
		return $this->typeConverter;
	}
}