<?php

namespace MultiVersion\network\proto\v419\packets\types\inventory\stackresponse;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

final class v419ItemStackResponseSlotInfo{
	public function __construct(
		private int $slot,
		private int $hotbarSlot,
		private int $count,
		private int $itemStackId,
		private string $customName,
		private int $durabilityCorrection
	){
	}

	public function getSlot() : int{ return $this->slot; }

	public function getHotbarSlot() : int{ return $this->hotbarSlot; }

	public function getCount() : int{ return $this->count; }

	public function getItemStackId() : int{ return $this->itemStackId; }

	public function getCustomName() : string{ return $this->customName; }

	public function getDurabilityCorrection() : int{ return $this->durabilityCorrection; }

	public static function read(PacketSerializer $in) : self{
		$slot = $in->getByte();
		$hotbarSlot = $in->getByte();
		$count = $in->getByte();
		$itemStackId = $in->readGenericTypeNetworkId();
		return new self($slot, $hotbarSlot, $count, $itemStackId, $customName ?? "", $durabilityCorrection ?? 0);
	}

	public function write(PacketSerializer $out) : void{
		$out->putByte($this->slot);
		$out->putByte($this->hotbarSlot);
		$out->putByte($this->count);
		$out->writeGenericTypeNetworkId($this->itemStackId);
	}
}
