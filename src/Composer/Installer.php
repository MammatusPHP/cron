<?php

declare(strict_types=1);

namespace Mammatus\Cron\Composer;

use Chimera\ExecuteCommand;
use Chimera\ExecuteQuery;
use Chimera\Mapping\Routing;
use Chimera\Routing\Handler as RoutingHandler;
use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Doctrine\Common\Annotations\AnnotationReader;
use Illuminate\Support\Collection;
use Mammatus\Http\Server\Annotations\Bus as BusAnnotation;
use Mammatus\Http\Server\Annotations\Vhost as VhostAnnotation;
use Mammatus\Http\Server\Annotations\WebSocket\Broadcast as BroadcastAnnotation;
use Mammatus\Http\Server\Annotations\WebSocket\Realm as RealmAnnotation;
use Mammatus\Http\Server\Annotations\WebSocket\Rpc as RpcAnnotation;
use Mammatus\Http\Server\Annotations\WebSocket\Subscription as SubscriptionAnnotation;
use Mammatus\Http\Server\Configuration\Bus;
use Mammatus\Http\Server\Configuration\Handler;
use Mammatus\Http\Server\Configuration\Server;
use Mammatus\Http\Server\Configuration\Vhost;
use Mammatus\Http\Server\Configuration\WebSocket\Broadcast;
use Mammatus\Http\Server\Configuration\WebSocket\Handler as WebSocketHandler;
use Mammatus\Http\Server\Configuration\WebSocket\Realm;
use Mammatus\Http\Server\Configuration\WebSocket\Rpc;
use Mammatus\Http\Server\Configuration\WebSocket\Subscription;
use React\EventLoop\StreamSelectLoop;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJsonAndInstalledJson;
use Roave\BetterReflection\SourceLocator\Type\Composer\Psr\Exception\InvalidPrefixMapping;
use Rx\Observable;
use Throwable;

use function ApiClients\Tools\Rx\observableFromArray;
use function array_key_exists;
use function array_map;
use function array_values;
use function assert;
use function Clue\React\Block\await;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function get_class;
use function is_array;
use function is_string;
use function is_subclass_of;
use function microtime;
use function round;
use function rtrim;
use function Safe\chmod;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\sprintf;
use function WyriHaximus\getIn;
use function WyriHaximus\iteratorOrArrayToArray;
use function WyriHaximus\listClassesInDirectories;
use function WyriHaximus\Twig\render;

use const DIRECTORY_SEPARATOR;

final class Installer implements PluginInterface, EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::PRE_AUTOLOAD_DUMP => 'findVhosts'];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // does nothing, see getSubscribedEvents() instead.
    }

    /**
     * Called before every dump autoload, generates a fresh PHP class.
     */
    public static function findActions(Event $event): void
    {
    }
}
