<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\static\GenericItemTranslator;
use MultiVersion\network\proto\static\IRuntimeBlockMapping;
use MultiVersion\network\proto\utils\NetItemConverter;
use MultiVersion\network\proto\v419\packets\types\inventory\v419ItemStackWrapper;
use pocketmine\network\mcpe\protocol\InventorySlotPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

class v419InventorySlotPacket extends InventorySlotPacket{

	private v419ItemStackWrapper $_item;

	public static function fromLatest(InventorySlotPacket $pk) : self{
		$npk = new self();
		$npk->windowId = $pk->windowId;
		$npk->inventorySlot = $pk->inventorySlot;
		$npk->_item = v419ItemStackWrapper::legacy($pk->item->getItemStack());
		return $npk;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		$this->windowId = $in->getUnsignedVarInt();
		$this->inventorySlot = $in->getUnsignedVarInt();
		$this->_item = v419ItemStackWrapper::read($in, true);
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putUnsignedVarInt($this->windowId);
		$out->putUnsignedVarInt($this->inventorySlot);
		$this->_item->write($out, true);
	}
}
