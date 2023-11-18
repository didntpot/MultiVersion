<?php

namespace MultiVersion\network\proto\v486\packets\types\inventory\stackresponse;

use MultiVersion\network\proto\v486\packets\types\inventory\v486ContainerUIIds;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseSlotInfo;
use function count;

final class v486ItemStackResponseContainerInfo{
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
		$containerId = v486ContainerUIIds::read($in);
		$slots = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$slots[] = ItemStackResponseSlotInfo::read($in);
		}
		return new self($containerId, $slots);
	}

	public function write(PacketSerializer $out) : void{
		v486ContainerUIIds::write($out, $this->containerId);
		$out->putUnsignedVarInt(count($this->slots));
		foreach($this->slots as $slot){
			$slot->write($out);
		}
	}
}
