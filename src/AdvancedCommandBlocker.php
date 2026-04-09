<?php

namespace AdvancedCommandBlocker;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\event\server\RemoteServerCommandEvent;
use pocketmine\level\sound\GhastShootSound;
use pocketmine\Player;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    /** @var Config */
    private $config;

    /** @var array */
    private $groups = [];

    /** @var array */
    private $groupOrder = ['illegal', 'admin', 'builder', 'staff', 'helper'];

    public function onEnable() {
        $this->getLogger()->info("§aAdvancedCommandBlocker activado.");
        @mkdir($this->getDataFolder());
        $this->loadConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    private function loadConfig() {
        $path = $this->getDataFolder() . "config.yml";
        $defaults = [
            'groups' => [
                'illegal' => [
                    'commands' => ['op', 'forceop', 'deop', 'setop', 'sudo', 'console'],
                    'message' => '§cNo puedes usar comandos de operador.',
                    'bypass-permission' => 'cmdblock.bypass.illegal'
                ],
                'admin' => [
                    'commands' => ['adminfun', 'nick', 'setgroup', 'addgroup', 'removegroup'],
                    'message' => '§cNo tienes permiso para usar comandos de administración.',
                    'bypass-permission' => 'cmdblock.bypass.admin'
                ],
                'builder' => [
                    'commands' => ['wand', 'pos1', 'pos2', 'set', 'replace', 'walls', 'cyl', 'hcyl'],
                    'message' => '§cNo puedes usar comandos de construcción.',
                    'bypass-permission' => 'cmdblock.bypass.builder'
                ],
                'staff' => [
                    'commands' => ['mod', 'staffmode', 'tpall', 'vanish', 'v', 'freeze', 'mute'],
                    'message' => '§cNo puedes usar comandos de staff.',
                    'bypass-permission' => 'cmdblock.bypass.staff'
                ],
                'helper' => [
                    'commands' => ['gamemode', 'gm', 'tp', 'teleport', 'heal', 'feed'],
                    'message' => '§cNo puedes usar comandos de helper.',
                    'bypass-permission' => 'cmdblock.bypass.helper'
                ]
            ],
            'block-console' => false,
            'log-to-file' => false
        ];
        $this->config = new Config($path, Config::YAML, $defaults);
        $this->config->save();

        $groupsData = $this->config->get('groups', []);
        foreach ($this->groupOrder as $groupName) {
            if (isset($groupsData[$groupName])) {
                $this->groups[$groupName] = $groupsData[$groupName];
                if (isset($this->groups[$groupName]['commands'])) {
                    $cmds = array_map('strtolower', $this->groups[$groupName]['commands']);
                    $this->groups[$groupName]['commands'] = $cmds;
                }
            }
        }
    }

    public function onPlayerCommand(PlayerCommandPreprocessEvent $event) {
        $player = $event->getPlayer();
        $message = $event->getMessage();

        if ($message[0] !== '/') {
            return;
        }

        $cmd = substr($message, 1);
        $parts = explode(' ', $cmd);
        $baseCmd = strtolower(array_shift($parts));

        $blockData = $this->isBlocked($baseCmd, $player);
        if ($blockData !== null) {
            $event->setCancelled(true);
            $player->sendMessage($blockData['message']);
            $player->getLevel()->addSound(new GhastShootSound($player), [$player]);
            $this->getLogger()->info("[CommandBlocker] Bloqueado {$player->getName()} intentó: /{$baseCmd}");
        }
    }

    public function onServerCommand(ServerCommandEvent $event) {
        if ($this->config->get('block-console', false) === false) {
            return;
        }
        $command = $event->getCommand();
        $parts = explode(' ', $command);
        $baseCmd = strtolower(array_shift($parts));
        if ($this->isBlocked($baseCmd, null) !== null) {
            $event->setCancelled(true);
            $this->getLogger()->warning("[CommandBlocker] Comando bloqueado para consola: /{$baseCmd}");
        }
    }

    public function onRemoteCommand(RemoteServerCommandEvent $event) {
        if ($this->config->get('block-console', false) === false) {
            return;
        }
        $command = $event->getCommand();
        $parts = explode(' ', $command);
        $baseCmd = strtolower(array_shift($parts));
        if ($this->isBlocked($baseCmd, null) !== null) {
            $event->setCancelled(true);
            $this->getLogger()->warning("[CommandBlocker] Comando bloqueado para RCON: /{$baseCmd}");
        }
    }

    private function isBlocked($command, $player = null) {
        $command = strtolower($command);
        foreach ($this->groupOrder as $groupName) {
            if (!isset($this->groups[$groupName])) continue;
            $group = $this->groups[$groupName];
            if (!in_array($command, $group['commands'])) continue;
            if ($player === null) {
                return [
                    'message' => $group['message'] ?? '§cComando bloqueado.'
                ];
            }
            if (!$player->hasPermission($group['bypass-permission'])) {
                return [
                    'message' => $group['message'] ?? '§cComando bloqueado.'
                ];
            }
            return null;
        }
        return null;
    }
}
