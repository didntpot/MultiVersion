<?php

namespace MultiVersion\network\proto\chunk\task;

use Closure;
use MultiVersion\MultiVersion;
use MultiVersion\network\proto\PacketTranslator;
use pocketmine\network\mcpe\compression\CompressBatchPromise;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\serializer\ChunkSerializer;
use pocketmine\scheduler\AsyncTask;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;

class MVChunkRequestTask extends AsyncTask{

	private const TLS_KEY_PROMISE = "promise";
	private const TLS_KEY_ERROR_HOOK = "errorHook";

	protected string $chunk;
	protected int $chunkX;
	protected int $chunkZ;
	/** @phpstan-var NonThreadSafeValue<Compressor> */
	protected NonThreadSafeValue $compressor;
	private string $tiles;

	private int $protocol;

	/**
	 * @param int                  $chunkX
	 * @param int                  $chunkZ
	 * @param Chunk                $chunk
	 * @param CompressBatchPromise $promise
	 * @param Compressor           $compressor
	 * @param PacketTranslator     $translator
	 * @param Closure|null         $onError
	 */
	public function __construct(int $chunkX, int $chunkZ, Chunk $chunk, CompressBatchPromise $promise, Compressor $compressor, PacketTranslator $translator, ?Closure $onError = null){
		$this->compressor = new NonThreadSafeValue($compressor);
		$this->chunk = FastChunkSerializer::serializeTerrain($chunk);
		$this->chunkX = $chunkX;
		$this->chunkZ = $chunkZ;
		$this->tiles = ChunkSerializer::serializeTiles($chunk);

		$this->protocol = $translator::PROTOCOL_VERSION;

		$this->storeLocal(self::TLS_KEY_PROMISE, $promise);
		$this->storeLocal(self::TLS_KEY_ERROR_HOOK, $onError);
	}

	public function onRun() : void{
		$translator = MultiVersion::getTranslator($this->protocol);

		$factory = $translator->getPacketSerializerFactory();
		$chunkSerializer = $factory->getChunkSerializer();

		$chunk = FastChunkSerializer::deserializeTerrain($this->chunk);
		$subCount = $chunkSerializer->getSubChunkCount($chunk);
		$payload = $chunkSerializer->serializeFullChunk($chunk, $translator->getTypeConverter()->getMVBlockTranslator(), $factory, $this->tiles);

		$stream = new BinaryStream();
		PacketBatch::encodePackets($stream, $factory->newSerializerContext(), [LevelChunkPacket::create(new ChunkPosition($this->chunkX, $this->chunkZ), $subCount, false, null, $payload)]);
		$this->setResult($this->compressor->deserialize()->compress($stream->getBuffer()));
	}

	public function onError() : void{
		/**
		 * @var Closure|null                    $hook
		 * @phpstan-var (Closure() : void)|null $hook
		 */
		$hook = $this->fetchLocal(self::TLS_KEY_ERROR_HOOK);
		if($hook !== null){
			$hook();
		}
	}

	public function onCompletion() : void{
		/** @var CompressBatchPromise $promise */
		$promise = $this->fetchLocal(self::TLS_KEY_PROMISE);
		$promise->resolve($this->getResult());
	}
}
