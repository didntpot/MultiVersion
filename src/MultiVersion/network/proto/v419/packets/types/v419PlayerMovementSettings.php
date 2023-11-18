<?php

namespace MultiVersion\network\proto\v419\packets\types;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;

final class v419PlayerMovementSettings{

	public static function fromLatest(PlayerMovementSettings $pk) : self{
		return new self($pk->getMovementType());
	}

	public function __construct(
		private int $movementType,
	){
	}

	public function getMovementType() : int{ return $this->movementType; }

	public static function read(PacketSerializer $in) : self{
		$movementType = $in->getVarInt();
		return new self($movementType);
	}

	public function write(PacketSerializer $out) : void{
		$out->putVarInt($this->movementType);
	}
}