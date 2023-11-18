<?php

namespace MultiVersion\network\proto\v419\packets\types\inventory\stackresponse;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseSlotInfo;
use function count;

final class v419ItemStackResponseContainerInfo{
	/**
	 * @param ItemStackResponseSlotInfo[] $slots
	 */
	public function __construct(
		private int $containerId,
		private array $slots
	){
	}

	public function getContainerId() : int{ return $this->containerId; }

	/** @return ItemStackResponseSlotInfo[] */
	public function getSlots() : array{ return $this->slots; }

	public static function read(PacketSerializer $in) : self{
		$containerId = $in->getByte();
		$slots = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$slot = v419ItemStackResponseSlotInfo::read($in);
			$slots[] = new ItemStackResponseSlotInfo($slot->getSlot(), $slot->getHotbarSlot(), $slot->getCount(), $slot->getItemStackId(), $slot->getCustomName(), $slot->getDurabilityCorrection());
		}
		return new self($containerId, $slots);
	}

	public function write(PacketSerializer $out) : void{
		$out->putByte($this->containerId);
		$out->putUnsignedVarInt(count($this->slots));
		foreach($this->slots as $slot){
			(new v419ItemStackResponseSlotInfo($slot->getSlot(), $slot->getHotbarSlot(), $slot->getCount(), $slot->getItemStackId(), $slot->getCustomName(), $slot->getDurabilityCorrection()))->write($out);
		}
	}
}
