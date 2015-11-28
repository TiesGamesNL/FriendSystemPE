<?php
namespace BrokenLens\FriendSystemPE;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;

class Main extends PluginBase implements Listener{
	public $request = array();
	public function onEnable(){
		$this->getLogger()->info("[FriendSystemPE] Added!"); // To Noob Developers: When the plugin is loaded it will show up this message.
		// To Noob Developers: Make sure if you want to do for example PlayerInteractEvent you have to do the code below.
		$this->getServer()->getPluginManager()->registerEvents($this ,$this);
		@mkdir($this->getDataFolder());
		@mkdir($this->getDataFolder()."players/");
	}
	// Events for FriendsSystem
	public function onDamageByPlayer(EntityDamageEvent $ev){
		// To Noob Developers: If you don't know what this part mean basically it's where if you get hit by a friend it will just block the damage and it will send an message.
		$cause = $ev->getCause();
		switch ($cause){
		case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
		$atkr = $ev->getDamager();
		$player = $ev->getEntity();
		if ($atkr instanceof Player and $player instanceof Player){
			if($this->isFriend($player, $atkr->getName())){
				$ev->setCancelled();
				$atkr->sendMessage(TextFormat::RED."There is no purpose on hurting your friend");
			}
		}
		break;
		}
	}
	
	public function onJoin(PlayerJoinEvent $ev){
		// To Noob Developers: This part means if you join the server. Whoever you added will still be in your friendslist.
		if (!file_exists($this->getDataFolder()."players/".$ev->getPlayer()->getName().".yml")){
			$config = new Config($this->getDataFolder()."players/".strtolower($ev->getPlayer()->getName()).".yml", Config::YAML);
			$config->set("friends", array());
			$config->save();
		}
	}
	// Commmands for FriendsSystem
	public function onCommand(CommandSender $sender,Command $command, $label,array $args){
		switch($command->getName()){
			// Main Command
			case "friend":
			if ($sender instanceof Player){
			if (isset($args[0])){
				switch ($args[0]){
					// Add Command
					case "add":
						if ($sender->hasPermission("friend.add")){
						if (isset($args[1])){
							$player = $this->getServer()->getPlayer($args[1]);
							if(!$player == null){
								$this->addRequest($player, $sender);
							}	else {
								$sender->sendMessage(TextFormat::RED."Player not found"); 
							}
						}
						return ;
						}{
							$sender->sendMessage(TextFormat::RED."You do not have permission for that command");
						}
					break;
					// Remove Command
					case "remove":
						if ($sender->hasPermission("friend.remove")){
						if (isset($args[1])){
							if ($this->removeFriend($sender, $args[1])){
								$sender->sendMessage("Friend removed");
							}else{
								$sender->sendMessage("Friend not found do /friend list \n to list your friends");
							}
						}else{
							$sender->sendMessage("Usage: /friend remove [name]");
						}
						return ;
						}else{
							$sender->sendMessage(TextFormat::RED."You do not have permission for that command");
						}
					break;
					// List Command
					case "list":
						if ($sender->hasPermission("friend.list")){
						$config = new Config($this->getDataFolder()."players/". strtolower($sender->getName()).".yml", Config::YAML);
						$array = $config->get("friends", []);
						$sender->sendMessage(TextFormat::GOLD.TextFormat::BOLD."Friends:");
						foreach ($array as $friendname){
							$sender->sendMessage(TextFormat::GREEN."* ".$friendname);
						}
						return ;
						}else {
							$sender->sendMessage(TextFormat::RED."You do not have permission for that command :(");
						}
					break;
					
				}
			}}else{
		$sender->sendMessage("Must use command in-game");
	}
			break;
			// Accept Command
			case "accept":
				if ($sender->hasPermission("friend.accept")){
				if (in_array($sender->getName(), $this->request)){
					foreach ($this->request as $target => $requestp){
						$target = $this->getServer()->getPlayer($target);
						$requestp = $this->getServer()->getPlayer($requestp);
						echo $target->getName().$requestp->getName();
						if ($requestp->getName() === $sender->getName()){
							$this->addFriend($target, $requestp);
							$this->addFriend($requestp, $target);
						}
						
					}
					return ;
				}else{
					$sender->sendMessage("No pending friend requests");
				}
				return ;
				}else{
					$sender->sendMessage(TextFormat::RED."You do not have permission for that command");
				}
			break;
		}
	}
	
	// API for FriendsSystem
	public function addRequest(Player $target,Player $requestp){
		// To Noob Developers: This right here will trigger a request. If the request is not accepted in 10 secs(20 * 10 20 ticks is 1 sec), The request will be canceled.
		if (!$this->isFriend($requestp, $target->getName())){
		$requestp->sendMessage("Sent request to ".$target->getName());
		$this->request[$requestp->getName()] = $target->getName();
		$target->sendMessage(TextFormat::GREEN.$requestp->getName()." has requested you as a friend do /accept to accept or ignore to ignore");
		echo var_dump($this->request);
 		$task = new Task($this, $target, $requestp);
 		$this->getServer()->getScheduler()->scheduleDelayedTask($task, 20*10);
 		return ;
		}else{
			$requestp->sendMessage("That player is already your friend :)");
		}
	}
	
	public function removeRequest(Player $target,Player $requestp, $reason){
		if (in_array($target->getName(), $this->request)){
			if ($reason == 0){
				$requestp->sendMessage(TextFormat::RED."Player ".$target->getName()." did not accept your friend request... :(");
			}
			unset($this->request[$requestp->getName()]);
		}
	}
	
	public function addFriend(Player $player,Player $friend){
		$player->sendMessage("added friend".$friend->getName());
		$friend->sendMessage("added friend ".$player->getName());
		$config = new Config($this->getDataFolder()."players/". strtolower($player->getName()).".yml", Config::YAML);
		$array = $config->get("friends", []);
		$array[] = $friend->getName();
		$config->set("friends", $array);
		$config->save();
		$this->removeRequest($friend, $player, 1);
	}
	
	public function removeFriend(Player $player, $friendname){
		if ($this->isFriend($player, $friendname)){
			$config = new Config($this->getDataFolder()."players/". strtolower($player->getName()).".yml", Config::YAML);
			$array = $config->get("friends", []);
			$id = array_search($friendname, $array);
			unset($array[$id]);
			$config->set("friends", $array);
			$config->save();
			return true;
		}
		return false;
	}
	
	public function isFriend(Player $player, $isfriendname){
		$config = new Config($this->getDataFolder()."players/". strtolower($player->getName()).".yml", Config::YAML);
		$array = $config->get("friends", []);
		if (in_array($isfriendname, $array)){
			return true;
		}
		return false;
	}
	
}
