<?php

namespace MultiVersion\network\proto\v486\packets\types\inventory;

use InvalidArgumentException;
use MultiVersion\network\proto\v486\v486TypeConverter;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;

class v486NetworkInventoryAction extends NetworkInventoryAction{

	public function read(PacketSerializer $packet) : NetworkInventoryAction{
		$this->sourceType = $packet->getUnsignedVarInt();

		switch($this->sourceType){
			case self::SOURCE_CONTAINER:
				$this->windowId = $packet->getVarInt();
				break;
			case self::SOURCE_WORLD:
				$this->sourceFlags = $packet->getUnsignedVarInt();
				break;
			case self::SOURCE_CREATIVE:
				break;
			case self::SOURCE_TODO:
				$this->windowId = $packet->getVarInt();
				break;
			default:
				throw new PacketDecodeException("Unknown inventory action source type $this->sourceType");
		}

		$this->inventorySlot = $packet->getUnsignedVarInt();
		$this->oldItem = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(v486TypeConverter::getInstance()->getTypeConverter()->netItemStackToCore(ItemStackWrapper::read($packet)->getItemStack())));
		$this->newItem = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(v486TypeConverter::getInstance()->getTypeConverter()->netItemStackToCore(ItemStackWrapper::read($packet)->getItemStack())));

		return $this;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function write(PacketSerializer $packet) : void{
		$packet->putUnsignedVarInt($this->sourceType);

		switch($this->sourceType){
			case self::SOURCE_CONTAINER:
				$packet->putVarInt($this->windowId);
				break;
			case self::SOURCE_WORLD:
				$packet->putUnsignedVarInt($this->sourceFlags);
				break;
			case self::SOURCE_CREATIVE:
				break;
			case self::SOURCE_TODO:
				$packet->putVarInt($this->windowId);
				break;
			default:
				throw new InvalidArgumentException("Unknown inventory action source type $this->sourceType");
		}

		$packet->putUnsignedVarInt($this->inventorySlot);
		$this->oldItem->write($packet);
		$this->newItem->write($packet);
	}
}