<?php

declare(strict_types=1);

namespace FlexPHP\Events;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * PSR-14 compatible Event Dispatcher.
 *
 * Combines both the EventDispatcherInterface and the ListenerProviderInterface
 * into a single concrete implementation, providing a simple but complete
 * event system for the FlexPHP framework.
 *
 * Usage:
 *   $dispatcher->listen(UserCreated::class, function (UserCreated $event) { ... });
 *   $dispatcher->dispatch(new UserCreated($user));
 *
 * Subscriber pattern:
 *   $dispatcher->subscribe(new UserSubscriber());
 *   // UserSubscriber::getListeners() returns [ UserCreated::class => [$listener] ]
 */
class EventDispatcher implements EventDispatcherInterface, ListenerProviderInterface
{
    /**
     * Registered listeners indexed by event class name.
     *
     * Structure: [ EventClass::class => [ callable, ... ] ]
     *
     * @var array<string, callable[]>
     */
    private array $listeners = [];

    // -------------------------------------------------------------------------
    // Listener registration
    // -------------------------------------------------------------------------

    /**
     * Register a single listener for the given event class.
     *
     * Listeners are invoked in registration order. The same callable may be
     * registered multiple times and will be called multiple times.
     *
     * @param string   $eventClass Fully-qualified class name of the event.
     * @param callable $listener   Callable that accepts an instance of $eventClass.
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Register all listeners declared by an event subscriber.
     *
     * The subscriber object must expose a public getListeners() method that
     * returns an associative array mapping event class names to arrays of
     * callables:
     *
     *   [ EventClass::class => [ $listener1, $listener2, ... ], ... ]
     *
     * @param object $subscriber Any object with a getListeners() method.
     */
    public function subscribe(object $subscriber): void
    {
        if (!method_exists($subscriber, 'getListeners')) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Subscriber %s must implement a public getListeners(): array method.',
                    get_class($subscriber)
                )
            );
        }

        /** @var array<string, callable[]> $map */
        $map = $subscriber->getListeners();

        foreach ($map as $eventClass => $listeners) {
            foreach ((array) $listeners as $listener) {
                $this->listen($eventClass, $listener);
            }
        }
    }

    // -------------------------------------------------------------------------
    // PSR-14: ListenerProviderInterface
    // -------------------------------------------------------------------------

    /**
     * Return all listeners registered for the given event, including listeners
     * registered for parent classes and interfaces.
     *
     * PSR-14 requires this method to return an iterable of callables.
     *
     * {@inheritDoc}
     *
     * @param object $event The event object.
     * @return iterable<callable> All applicable listeners.
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventClass = get_class($event);
        $applicable = [];

        foreach ($this->listeners as $registeredClass => $listeners) {
            // Match on exact class, parent classes, and implemented interfaces
            if ($event instanceof $registeredClass) {
                foreach ($listeners as $listener) {
                    $applicable[] = $listener;
                }
            }
        }

        return $applicable;
    }

    // -------------------------------------------------------------------------
    // PSR-14: EventDispatcherInterface
    // -------------------------------------------------------------------------

    /**
     * Dispatch an event to all applicable listeners.
     *
     * If the event implements StoppableEventInterface and isPropagationStopped()
     * returns true at any point, subsequent listeners are not called.
     *
     * Returns the same event object that was passed in, potentially modified by
     * the listeners (as per PSR-14).
     *
     * {@inheritDoc}
     *
     * @param object $event The event to dispatch.
     * @return object The (possibly modified) event object.
     */
    public function dispatch(object $event): object
    {
        $stoppable = $event instanceof StoppableEventInterface;

        foreach ($this->getListenersForEvent($event) as $listener) {
            // Respect the stoppable interface before each listener call
            if ($stoppable && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }

    // -------------------------------------------------------------------------
    // Introspection helpers
    // -------------------------------------------------------------------------

    /**
     * Return all registered listeners, keyed by event class name.
     *
     * Useful for debugging and testing.
     *
     * @return array<string, callable[]>
     */
    public function getAll(): array
    {
        return $this->listeners;
    }

    /**
     * Remove all listeners for a specific event class.
     *
     * @param string $eventClass Fully-qualified event class name.
     */
    public function forget(string $eventClass): void
    {
        unset($this->listeners[$eventClass]);
    }

    /**
     * Remove all registered listeners.
     */
    public function flush(): void
    {
        $this->listeners = [];
    }
}
