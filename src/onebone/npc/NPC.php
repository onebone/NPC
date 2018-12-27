<?php

/*
 * Copyright (C) 2015-2018 onebone <jyc00410@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace onebone\npc;

use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\level\Location;
use pocketmine\math\Vector2;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\utils\UUID;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;

class NPC extends Location{
	/** @var  Main */
	private $plugin;
	private $eid;
	/** @var Skin */
	private $skin;
	private $name;
	private $item;
	private $message, $command;

	private $uuid;

	public function __construct(Main $plugin, Location $loc, $name, Skin $skin, Item $item, $message = "", $command = null){
		parent::__construct($loc->x, $loc->y, $loc->z, $loc->yaw, $loc->pitch, $loc->level);

		$this->plugin = $plugin;

		$this->eid = Entity::$entityCount++;
		$this->skin = $skin;
		$this->name = $name;
		$this->item = $item;
		$this->message = $message;
		$this->command = $command;

		$this->uuid = UUID::fromRandom();
	}

	public function getName(){
		return $this->name;
	}

	public function setMessage($msg){
		$this->message = $msg;
	}

	public function getMessage(){
		return $this->message;
	}

	public function setCommand($command){
		if(trim($command) === ""){
			$this->command = $command;
		}

		$this->command = $command;
	}

	public function getCommand(){
		return $this->command;
	}

	public function getSkin(){
		return $this->skin;
	}

	public function setHoldingItem(Item $item){
		$this->item = clone $item;
	}

	public function getId(){
		return $this->eid;
	}

	public function onInteract(Player $player){
		if($this->message !== ""){
			$player->sendMessage($this->message);
		}

		if($this->command !== null){
			$this->plugin->getServer()->dispatchCommand($player, $this->command);
		}
	}

	public function seePlayer(Player $target){
		$pk = new MovePlayerPacket();
		$pk->entityRuntimeId = $this->eid;
		if($this->yaw === -1 and $target !== null){
			$xdiff = $target->x - $this->x;
			$zdiff = $target->z - $this->z;
			$angle = atan2($zdiff, $xdiff);
			$pk->yaw = (($angle * 180) / M_PI) - 90;
		}else{
			$pk->yaw = $this->yaw;
		}
		if($this->pitch === -1 and $target !== null){
			$ydiff = $target->y - $this->y;

			$vec = new Vector2($this->x, $this->z);
			$dist = $vec->distance($target->x, $target->z);
			$angle = atan2($dist, $ydiff);
			$pk->pitch = (($angle * 180) / M_PI) - 90;
		}else{
			$pk->pitch = $this->pitch;
		}
		$pk->position = $this->asLocation()->add(0, 1.62, 0);
		$pk->headYaw = $pk->yaw;

		$target->sendDataPacket($pk);
	}

	public function spawnTo(Player $target){
		$pk = new AddPlayerPacket();
		$pk->uuid = $this->uuid;
		$pk->username = $this->name;
		$pk->entityRuntimeId = $this->eid;
		$pk->position = $this->asVector3();
		if($this->yaw === -1 and $target !== null){
			$xdiff = $target->x - $this->x;
			$zdiff = $target->z - $this->z;
			$angle = atan2($zdiff, $xdiff);
			$pk->yaw = (($angle * 180) / M_PI) - 90;
		}else{
			$pk->yaw = $this->yaw;
		}
		if($this->pitch === -1 and $target !== null){

		}else{
			$pk->pitch = $this->pitch;
		}
		$pk->item = $this->item;
		$pk->metadata =
		[
			Entity::DATA_FLAGS => [
				Entity::DATA_TYPE_LONG, 1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG
										^ 1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG
			],
			Entity::DATA_NAMETAG => [
					Entity::DATA_TYPE_STRING, $this->name
			],
			Entity::DATA_LEAD_HOLDER_EID => [
						Entity::DATA_TYPE_LONG, -1
			]
		];
		$target->sendDataPacket($pk);

		$pk = new PlayerListPacket();
		$pk->type = PlayerListPacket::TYPE_ADD;

		$name = TextFormat::GRAY."NPC: ".$this->name;
		$pk->entries = [
			PlayerListEntry::createAdditionEntry(
				$this->uuid, $this->eid, $name, $this->skin
			)
		];

		$target->sendDataPacket($pk);
	}

	public function removeFrom(Player $player){
		$pk = new RemoveEntityPacket();
		$pk->entityUniqueId = $this->eid;

		$player->sendDataPacket($pk);

		$pk = new PlayerListPacket();
		$pk->type = PlayerListPacket::TYPE_REMOVE;

		$name = TextFormat::GRAY."NPC: ".$this->name;
		$pk->entries = [
			PlayerListEntry::createAdditionEntry(
				$this->uuid, $this->eid, $name, $this->skin
			)
		];
		$player->sendDataPacket($pk);
	}

	public function remove(){
		foreach($this->level->getPlayers() as $player){
			$this->removeFrom($player);
		}
	}

	public function getSaveData(){
		return [
			$this->x, $this->y, $this->z, $this->level->getFolderName(),
			$this->yaw, $this->pitch,
			$this->eid, $this->item->getId(), $this->item->getDamage(), $this->name, $this->skin->getSkinId(),
			$this->message, $this->command
		];
	}

	public static function createNPC(Main $plugin, $data){
		$plugin->getServer()->loadLevel($data[3]);

		return new NPC($plugin, new Location($data[0], $data[1], $data[2], $data[4], $data[5], $plugin->getServer()->getLevelByName($data[3])), // location
			$data[9], // name
			new Skin($data[10], $data[6]),
			Item::get($data[7], $data[8]), // item
			$data[11], // message
			$data[12] ?? null // command
		);
	}
}
