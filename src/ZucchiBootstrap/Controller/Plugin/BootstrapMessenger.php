<?php
/**
 * ZucchiBootstrap (http://zucchi.co.uk)
 *
 * @link      http://github.com/zucchi/ZucchiBootstrap for the canonical source repository
 * @copyright Copyright (c) 2012 Zucchi Limited (http://zucchi.co.uk)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZucchiBootstrap\Controller\Plugin;


use ArrayIterator;
use Countable;
use IteratorAggregate;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Session\Container;
use Zend\Session\ManagerInterface as Manager;
use Zend\Session\SessionManager;
use Zend\Stdlib\SplQueue;

/**
 * Clone of FlashMessenger plugin adapted to work with Bootstrap
 * 
 * @author Matt Cockayne <matt@zucchi.co.uk>
 * @package ZucchiBootstrap
 * @subpackage Controller
 * @category Plugin
 */
class BootstrapMessenger extends AbstractPlugin implements IteratorAggregate, Countable
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * Messages from previous request
     * @var array
     */
    protected $messages = array();

    /**
     * @var Manager
     */
    protected $session;

    /**
     * Whether a message has been added during this request
     *
     * @var boolean
     */
    protected $messageAdded = false;

    /**
     * Instance namespace, default is 'bootstrap'
     *
     * @var string
     */
    protected $namespace = 'bootstrap';

    /**
     * Set the session manager
     *
     * @param  Manager $manager
     * @return BootstrapMessenger
     */
    public function setSessionManager(Manager $manager)
    {
        $this->session = $manager;
        return $this;
    }

    /**
     * Retrieve the session manager
     *
     * If none composed, lazy-loads a SessionManager instance
     *
     * @return Manager
     */
    public function getSessionManager()
    {
        if (!$this->session instanceof Manager) {
            $this->setSessionManager(new SessionManager());
        }
        return $this->session;
    }

    /**
     * Get session container for flash messages
     *
     * @return Container
     */
    public function getContainer()
    {
        if ($this->container instanceof Container) {
            return $this->container;
        }

        $manager = $this->getSessionManager();
        $this->container = new Container('BootstrapMessenger', $manager);
        return $this->container;
    }

    /**
     * Change the namespace messages are added to
     *
     * Useful for per action controller messaging between requests
     *
     * @param  string $namespace
     * @return BootstrapMessenger Provides a fluent interface
     */
    public function setNamespace($namespace = 'bootstrap')
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Get the message namespace
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Add a message
     *
     * @param  string $message
     * @return BootstrapMessenger Provides a fluent interface
     */
    public function addMessage($message, $status = 'block', $title = false)
    {
        $container = $this->getContainer();
        $namespace = $this->getNamespace();

        if (!$this->messageAdded) {
            $this->getMessagesFromContainer();
            $container->setExpirationHops(1, null, true);
        }

        if (!isset($container->{$namespace}->{$status})
            || !($container->{$namespace}->{$status} instanceof SplQueue)
        ) {
            $container->{$namespace}->{$status} = new SplQueue();
        }

        $container->{$namespace}->{$status}->push($message);

        $this->messageAdded = true;
        return $this;
    }

    /**
     * Whether a specific namespace has messages
     *
     * @return boolean
     */
    public function hasMessages()
    {
        $this->getMessagesFromContainer();
        return isset($this->messages[$this->getNamespace()]);
    }

    /**
     * Get messages from a specific namespace
     *
     * @return array
     */
    public function getMessages()
    {
        if ($this->hasMessages()) {
            return $this->messages[$this->getNamespace()]->toArray();
        }

        return array();
    }

    /**
     * Clear all messages from the previous request & current namespace
     *
     * @return boolean True if messages were cleared, false if none existed
     */
    public function clearMessages()
    {
        if ($this->hasMessages()) {
            unset($this->messages[$this->getNamespace()]);
            return true;
        }

        return false;
    }

    /**
     * Check to see if messages have been added to the current
     * namespace within this request
     *
     * @return boolean
     */
    public function hasCurrentMessages()
    {
        $container = $this->getContainer();
        $namespace = $this->getNamespace();
        return isset($container->{$namespace});
    }

    /**
     * Get messages that have been added to the current
     * namespace within this request
     *
     * @return array
     */
    public function getCurrentMessages()
    {
        if ($this->hasCurrentMessages()) {
            $container = $this->getContainer();
            $namespace = $this->getNamespace();
            return $container->{$namespace}->toArray();
        }

        return array();
    }

    /**
     * Clear messages from the current request and current namespace
     *
     * @return boolean
     */
    public function clearCurrentMessages()
    {
        if ($this->hasCurrentMessages()) {
            $container = $this->getContainer();
            $namespace = $this->getNamespace();
            unset($container->{$namespace});
            return true;
        }

        return false;
    }

    /**
     * Complete the IteratorAggregate interface, for iterating
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        if ($this->hasMessages()) {
            return new ArrayIterator($this->getMessages());
        }

        return new ArrayIterator();
    }

    /**
     * Complete the countable interface
     *
     * @return int
     */
    public function count()
    {
        if ($this->hasMessages()) {
            return count($this->getMessages());
        }

        return 0;
    }

    /**
     * Pull messages from the session container
     *
     * Iterates through the session container, removing messages into the local
     * scope.
     *
     * @return void
     */
    protected function getMessagesFromContainer()
    {
        if (!empty($this->messages) || $this->messageAdded) {
            return;
        }

        $container = $this->getContainer();

        $namespaces = array();
        foreach ($container as $namespace => $messages) {
            $this->messages[$namespace] = $messages;
            $namespaces[] = $namespace;
        }

        foreach ($namespaces as $namespace) {
            unset($container->{$namespace});
        }
    }
}