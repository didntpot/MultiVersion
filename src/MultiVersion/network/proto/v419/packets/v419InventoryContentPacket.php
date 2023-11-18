<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\static\GenericItemTranslator;
use MultiVersion\network\proto\static\IRuntimeBlockMapping;
use MultiVersion\network\proto\utils\NetItemConverter;
use MultiVersion\network\proto\v419\packets\types\inventory\v419ItemStackWrapper;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;

class v419InventoryContentPacket extends InventoryContentPacket{

	private array $_items = [];

	public static function fromLatest(InventoryContentPacket $pk) : self{
		$npk = new self();
		$npk->windowId = $pk->windowId;
		$npk->_items = array_map(function(ItemStackWrapper $netItemStack) : v419ItemStackWrapper{
			return v419ItemStackWrapper::legacy($netItemStack->getItemStack());
		}, $pk->items);
		return $npk;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		$this->windowId = $in->getUnsignedVarInt();
		$count = $in->getUnsignedVarInt();
		for($i = 0; $i < $count; ++$i){
			$this->_items[] = v419ItemStackWrapper::read($in, true);
		}
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putUnsignedVarInt($this->windowId);
		$out->putUnsignedVarInt(count($this->_items));
		foreach($this->_items as $item){
			$item->write($out, true);
		}
	}
}
