<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\v419\packets\types\resourcepacks\v419ResourcePackInfoEntry;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\resourcepacks\BehaviorPackInfoEntry;

class v419ResourcePacksInfoPacket extends ResourcePacksInfoPacket{

	/** @var v419ResourcePackInfoEntry[] */
	public array $_resourcePackEntries = [];

	public static function fromLatest(ResourcePacksInfoPacket $pk) : self{
		$npk = new self();
		$npk->mustAccept = $pk->mustAccept;
		$npk->hasScripts = $pk->hasScripts;
		$npk->_resourcePackEntries = array_map([v419ResourcePackInfoEntry::class, "fromLatest"], $pk->resourcePackEntries);
		$npk->behaviorPackEntries = $pk->behaviorPackEntries;
		return $npk;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		$this->mustAccept = $in->getBool();
		$this->hasScripts = $in->getBool();
		$behaviorPackCount = $in->getLShort();
		while($behaviorPackCount-- > 0){
			$this->behaviorPackEntries[] = BehaviorPackInfoEntry::read($in);
		}

		$resourcePackCount = $in->getLShort();
		while($resourcePackCount-- > 0){
			$this->_resourcePackEntries[] = v419ResourcePackInfoEntry::read($in);
		}
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putBool($this->mustAccept);
		$out->putBool($this->hasScripts);
		$out->putLShort(count($this->behaviorPackEntries));
		foreach($this->behaviorPackEntries as $entry){
			$entry->write($out);
		}
		$out->putLShort(count($this->_resourcePackEntries));
		foreach($this->_resourcePackEntries as $entry){
			$entry->write($out);
		}
	}
}