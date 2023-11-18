<?php

namespace MultiVersion\network\proto;

use Closure;
use JsonMapper;
use JsonMapper_Exception;
use MultiVersion\MultiVersion;
use MultiVersion\network\MVNetworkSession;
use pocketmine\network\mcpe\handler\LoginPacketHandler;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\login\ClientData;
use pocketmine\network\PacketHandlingException;
use pocketmine\Server;
use ReflectionException;

class MVLoginPacketHandler extends LoginPacketHandler{

	private MVNetworkSession $session;

	public function __construct(Server $server, MVNetworkSession $session, Closure $playerInfoConsumer, Closure $authCallback){
		$this->session = $session;
		parent::__construct($server, $session, $playerInfoConsumer, $authCallback);
	}

	/**
	 * @throws ReflectionException
	 */
	public function handleLogin(LoginPacket $packet) : bool{
		if(!in_array($packet->protocol, MultiVersion::getProtocols(), true)){
			$this->session->disconnectIncompatibleProtocol($packet->protocol);
			return false;
		}
		$this->session->setPacketTranslator(MultiVersion::getTranslator($packet->protocol));
		if($packet->protocol !== ProtocolInfo::CURRENT_PROTOCOL){
			$this->session->getLogger()->info("Translating packets from protocol $packet->protocol");
			$packet->protocol = ProtocolInfo::CURRENT_PROTOCOL; // hack, jk this entire thing is a hack lmao
		}
		return parent::handleLogin($packet);
	}

	/**
	 * @throws PacketHandlingException
	 */
	protected function parseClientData(string $clientDataJwt) : ClientData{
		try{
			[, $clientDataClaims,] = JwtUtils::parse($clientDataJwt);
		}catch(JwtException $e){
			throw PacketDecodeException::wrap($e);
		}

		$this->session->getPacketTranslator()->injectClientData($clientDataClaims);

		$mapper = new JsonMapper;
		$mapper->bEnforceMapType = false; //TODO: we don't really need this as an array, but right now we don't have enough models
		$mapper->bExceptionOnMissingData = true;
		$mapper->bExceptionOnUndefinedProperty = true;
		try{
			$clientData = $mapper->map($clientDataClaims, new ClientData);
		}catch(JsonMapper_Exception $e){
			throw PacketDecodeException::wrap($e);
		}
		return $clientData;
	}
}
