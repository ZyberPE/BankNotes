<?php

namespace BankNotes;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Config;
use pocketmine\item\StringToItemParser;
use onebone\economyapi\EconomyAPI;
use pocketmine\nbt\tag\CompoundTag;

class Main extends PluginBase implements Listener {

    private Config $config;

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if(!$sender instanceof Player){
            return true;
        }

        if($command->getName() === "withdrawl"){

            if(!isset($args[0])){
                $sender->sendMessage($this->config->getNested("messages.usage"));
                return true;
            }

            $amount = (int)$args[0];

            if($amount <= 0){
                $sender->sendMessage($this->config->getNested("messages.invalid"));
                return true;
            }

            $economy = EconomyAPI::getInstance();

            if($economy->myMoney($sender) < $amount){
                $sender->sendMessage($this->config->getNested("messages.not-enough"));
                return true;
            }

            $economy->reduceMoney($sender, $amount);

            $note = VanillaItems::PAPER();
            $name = str_replace(
                ["{player}", "{amount}"],
                [$sender->getName(), $amount],
                $this->config->get("note-name")
            );

            $lore = [];
            foreach($this->config->get("note-lore") as $line){
                $lore[] = str_replace(
                    ["{player}", "{amount}"],
                    [$sender->getName(), $amount],
                    $line
                );
            }

            $note->setCustomName($name);
            $note->setLore($lore);

            $nbt = $note->getNamedTag();
            $nbt->setInt("banknote_value", $amount);
            $note->setNamedTag($nbt);

            $sender->getInventory()->addItem($note);

            $sender->sendMessage(
                str_replace("{amount}", $amount, $this->config->getNested("messages.withdraw-success"))
            );
        }

        return true;
    }

    public function onRedeem(PlayerInteractEvent $event): void {

        $player = $event->getPlayer();
        $item = $event->getItem();

        if($item->getNamedTag()->getTag("banknote_value") !== null){

            $amount = $item->getNamedTag()->getInt("banknote_value");

            EconomyAPI::getInstance()->addMoney($player, $amount);

            $item->pop();
            $player->getInventory()->setItemInHand($item);

            $player->sendMessage(
                str_replace("{amount}", $amount, $this->config->getNested("messages.redeem"))
            );
        }
    }
}
