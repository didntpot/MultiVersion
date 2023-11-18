<?php

namespace MultiVersion\network\proto\v419\packets;

use MultiVersion\network\proto\utils\ReflectionUtils;
use pocketmine\network\mcpe\protocol\EducationSettingsPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;

class v419EducationSettingsPacket extends EducationSettingsPacket{

	public static function fromLatest(EducationSettingsPacket $pk) : self{
		$npk = new self();
		ReflectionUtils::setProperty(EducationSettingsPacket::class, $npk, "codeBuilderDefaultUri", $pk->getCodeBuilderDefaultUri());
		ReflectionUtils::setProperty(EducationSettingsPacket::class, $npk, "codeBuilderTitle", $pk->getCodeBuilderTitle());
		ReflectionUtils::setProperty(EducationSettingsPacket::class, $npk, "canResizeCodeBuilder", $pk->canResizeCodeBuilder());
		ReflectionUtils::setProperty(EducationSettingsPacket::class, $npk, "codeBuilderOverrideUri", $pk->getCodeBuilderOverrideUri());
		ReflectionUtils::setProperty(EducationSettingsPacket::class, $npk, "hasQuiz", $pk->getHasQuiz());
		return $npk;
	}

	protected function decodePayload(PacketSerializer $in) : void{
		ReflectionUtils::setProperty(EducationSettingsPacket::class, $this, "codeBuilderDefaultUri", $in->getString());
		ReflectionUtils::setProperty(EducationSettingsPacket::class, $this, "codeBuilderTitle", $in->getString());
		ReflectionUtils::setProperty(EducationSettingsPacket::class, $this, "canResizeCodeBuilder", $in->getBool());
		ReflectionUtils::setProperty(EducationSettingsPacket::class, $this, "codeBuilderOverrideUri", $in->readOptional(fn() => $in->getString()));
		ReflectionUtils::setProperty(EducationSettingsPacket::class, $this, "hasQuiz", $in->getBool());
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putString($this->getCodeBuilderDefaultUri());
		$out->putString($this->getCodeBuilderTitle());
		$out->putBool($this->canResizeCodeBuilder());
		$out->writeOptional($this->getCodeBuilderOverrideUri(), fn(string $v) => $out->putString($v));
		$out->putBool($this->getHasQuiz());
	}
}
