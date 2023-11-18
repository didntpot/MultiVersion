<?php

namespace MultiVersion\network\proto\v419\packets\types\inventory;


use InvalidArgumentException;
use MultiVersion\network\proto\v419\v419TypeConverter;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\utils\BinaryDataException;

class v419NetworkInventoryAction extends NetworkInventoryAction{
	public const SOURCE_CRAFT_SLOT = 100;
	public int $newItemStackId;

	/**
	 * @return $this
	 *
	 * @throws BinaryDataException
	 * @throws PacketDecodeException
	 */
	public function read(PacketSerializer $packet, bool $hasItemStackIds = false) : v419NetworkInventoryAction{
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
			case self::SOURCE_CRAFT_SLOT:
			case self::SOURCE_TODO:
				$this->windowId = $packet->getVarInt();
				break;
			default:
				throw new PacketDecodeException("Unknown inventory action source type $this->sourceType");
		}

		$this->inventorySlot = $packet->getUnsignedVarInt();

		$this->oldItem = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(v419TypeConverter::getInstance()->getTypeConverter()->netItemStackToCore(v419ItemStackWrapper::read($packet)->getItemStack())));
		$this->newItem = ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(v419TypeConverter::getInstance()->getTypeConverter()->netItemStackToCore(v419ItemStackWrapper::read($packet)->getItemStack())));

		if($hasItemStackIds){
			$this->newItemStackId = $packet->readGenericTypeNetworkId();
		}

		return $this;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function write(PacketSerializer $packet, bool $hasItemStackIds = false) : void{
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
			case self::SOURCE_CRAFT_SLOT:
			case self::SOURCE_TODO:
				$packet->putVarInt($this->windowId);
				break;
			default:
				/** @phpstan-ignore-next-line */
				throw new InvalidArgumentException("Unknown inventory action source type $this->sourceType");
		}

		$packet->putUnsignedVarInt($this->inventorySlot);
		v419ItemStackWrapper::legacy($this->oldItem->getItemStack())->write($packet);
		v419ItemStackWrapper::legacy($this->newItem->getItemStack())->write($packet);
		if($hasItemStackIds){
			$packet->writeGenericTypeNetworkId($this->newItemStackId);
		}
	}
}