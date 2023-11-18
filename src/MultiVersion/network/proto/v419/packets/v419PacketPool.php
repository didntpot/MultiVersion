<?php

namespace MultiVersion\network\proto\v419\packets;

use pocketmine\network\mcpe\protocol\PacketPool;

class v419PacketPool extends PacketPool{

	public function __construct(){
		parent::__construct();
		// override other packets
		$this->registerPacket(new v419ActorPickRequestPacket());
		$this->registerPacket(new v419AdventureSettingsPacket());
		$this->registerPacket(new v419CommandRequestPacket());
		$this->registerPacket(new v419EmotePacket());
		$this->registerPacket(new v419InventoryTransactionPacket());
		$this->registerPacket(new v419ItemStackRequestPacket());
		$this->registerPacket(new v419MapInfoRequestPacket());
		$this->registerPacket(new v419ModalFormResponsePacket());
		$this->registerPacket(new v419NpcRequestPacket());
		$this->registerPacket(new v419PlayerActionPacket());
		$this->registerPacket(new v419PlayerAuthInputPacket());
		$this->registerPacket(new v419RequestChunkRadiusPacket());
		$this->registerPacket(new v419SetActorDataPacket());
	}
}