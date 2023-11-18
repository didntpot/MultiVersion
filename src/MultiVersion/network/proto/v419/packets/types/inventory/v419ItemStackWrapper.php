<?php

namespace MultiVersion\network\proto\v419\packets\types\inventory;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;

final class v419ItemStackWrapper{
	public function __construct(
		private int $stackId,
		private ItemStack $itemStack
	){
	}

	public static function legacy(ItemStack $itemStack) : self{
		return new self($itemStack->getId() === 0 ? 0 : 1, $itemStack);
	}

	public function getStackId() : int{ return $this->stackId; }

	public function getItemStack() : ItemStack{ return $this->itemStack; }

	public static function read(PacketSerializer $in, bool $hasLegacyNetId = false) : self{
		if($hasLegacyNetId){
			$stackId = $in->readGenericTypeNetworkId();
			$stack = $in->getItemStackWithoutStackId();
			return new self($stackId, $stack);
		}

		$stack = $in->getItemStackWithoutStackId();
		return self::legacy($stack);
	}

	public function write(PacketSerializer $out, bool $hasLegacyNetId = false) : void{
		if($hasLegacyNetId){
			$out->writeGenericTypeNetworkId($this->stackId);
		}
		$closure = function() : void{
			//NOOP
		};
		$out->putItemStack($this->itemStack, $closure);
	}
}
