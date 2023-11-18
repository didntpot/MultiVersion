<?php

namespace MultiVersion;

use InvalidArgumentException;
use MultiVersion\network\MVRakNetProtocolAcceptor;
use MultiVersion\network\proto\latest\LatestProtocol;
use MultiVersion\network\proto\PacketTranslator;
use MultiVersion\network\proto\v419\v419PacketTranslator;
use MultiVersion\network\proto\v486\v486PacketTranslator;
use pocketmine\utils\RegistryTrait;

class MultiVersion{
	use RegistryTrait;

	const SERVER_AUTH_INVENTORY = false;

	protected static function setup() : void{
		foreach([
			new LatestProtocol(),
			new v486PacketTranslator(),
			new v419PacketTranslator(),
		] as $translator){
			self::register($translator::PROTOCOL_VERSION, $translator);
		}
	}

	protected static function register(int $name, PacketTranslator $member) : void{
		self::_registryRegister("v$name", $member);
	}

	/**
	 * @return PacketTranslator
	 * @throws InvalidArgumentException
	 */
	public static function getTranslator(int $protocol) : object{
		self::checkInit();
		$upperName = mb_strtoupper("v$protocol");
		if(!isset(self::$members[$upperName])){
			throw new InvalidArgumentException("No such registry member: " . self::class . "::" . $upperName);
		}
		return self::preprocessMember(self::$members[$upperName]);
	}

	/**
	 * @return PacketTranslator[]
	 * @phpstan-return array<string, PacketTranslator>
	 */
	public static function getTranslators() : array{
		/** @var PacketTranslator[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

    public static function getProtocols() : array{
        return array_unique(array_map(function(PacketTranslator $translator) : int{
            return $translator::PROTOCOL_VERSION;
        }, self::getTranslators()));
    }

	public static function getRaknetAcceptor() : MVRakNetProtocolAcceptor{
		return new MVRakNetProtocolAcceptor(
			array_unique(array_map(function(PacketTranslator $translator) : int{
				return $translator::RAKNET_VERSION;
			}, self::getTranslators()))
		);
	}
}