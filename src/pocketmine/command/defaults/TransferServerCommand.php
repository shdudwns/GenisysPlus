<?php

/*
 *  _______                                     ______  _
 * /  ____ \                                   |  __  \| \
 * | |    \_|              _                   | |__| || |
 * | |   ___  ___  _  ___ (_) ___  __    _ ___ |  ____/| | _   _  ___
 * | |  |_  |/(_)\| '/_  || |/___\(_)\  ///___\| |     | || | | |/___\
 * | \___|| | |___| |  | || |_\_\   \ \// _\_\ | |     | || | | |_\_\
 * \______/_|\___/|_|  |_||_|\___/   \ /  \___/|_|     |_||__/,_|\___/
 *                                   //
 *                                  (_)                Power by:
 *                                                           Pocketmine-MP
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @由Pocketmine-MP团队创建，GenisysPlus项目组修改
 * @链接 http://www.pocketmine.net/
 * @链接 https://github.com/Tcanw/GenisysPlus
 *
*/

namespace pocketmine\command\defaults;

use pocketmine\network\protocol\TransferPacket;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;

class TransferServerCommand extends VanillaCommand {

	/**
	 * TransferServerCommand constructor.
	 *
	 * @param $name
	 */
	public function __construct($name){
		parent::__construct(
			$name,
			"将玩家传送至另一个服务器",
			"/transferserver <player玩家> <address地址> [port端口]",
			["transferserver"]
		);
		$this->setPermission("pocketmine.command.transfer");
	}

	/**
	 * @param CommandSender $sender
	 * @param string        $currentAlias
	 * @param array         $args
	 *
	 * @return bool
	 */
	public function execute(CommandSender $sender, $currentAlias, array $args){
		$address = null;
		$port = null;
		$player = null;
		if($sender instanceof Player){
			if(!$this->testPermission($sender)){
				return true;
			}

			if(count($args) <= 0){
				$sender->sendMessage("Usage: /transferserver <address> [port]");
				return false;
			}

			$address = strtolower($args[0]);
			$port = (isset($args[1]) && is_numeric($args[1]) ? $args[1] : 19132);

			$pk = new TransferPacket();
			$pk->address = $address;
			$pk->port = $port;
			$sender->dataPacket($pk);

			return false;
		}

		if(count($args) <= 1){
			$sender->sendMessage("Usage: /transferserver <player> <address> [port]");
			return false;
		}

		if(!($player = Server::getInstance()->getPlayer($args[0])) instanceof Player){
			$sender->sendMessage("Player specified not found!");
			return false;
		}

		$address = strtolower($args[1]);
		$port = (isset($args[2]) && is_numeric($args[2]) ? $args[2] : 19132);

		$sender->sendMessage("Sending " . $player->getName() . " to " . $address . ":" . $port);

		$pk = new TransferPacket();
		$pk->address = $address;
		$pk->port = $port;
		$player->dataPacket($pk);
	}

}
