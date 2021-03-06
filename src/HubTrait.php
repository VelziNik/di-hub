<?php
namespace Nayjest\DI;

use Nayjest\DI\Exception\AlreadyDefinedException;
use Nayjest\DI\Exception\NotFoundException;
use Nayjest\DI\Internal\ComponentMethodNaming;
use Nayjest\DI\Internal\Definition;
use Nayjest\DI\Internal\Item;

trait HubTrait
{
    /**
     * @var Component[][]
     */
    private $extensions = [];

    /**
     * @var Item[]
     */
    private $items = [];

    /**
     * Adds component to hub.
     *
     * @api
     * @param ComponentInterface $component
     * @return $this
     * @throws \Exception
     */
    public function add(ComponentInterface $component)
    {
        $definitions = [];
        $extensions = [];
        /** @var HubInterface|self $this */
        $component->handle(Hub::MESSAGE_REGISTER, [
            new ComponentDefinitions(
                $definitions,
                $extensions
            ),
            $this
        ]);

        $this->registerExtensions($extensions, $component);
        foreach ($definitions as $definition) {
            $this->registerDefinition($definition, $component);
        }
        return $this;
    }

    /**
     * @param ComponentInterface[] $components
     * @return $this
     */
    public function addMany(array $components)
    {
        foreach ($components as $component) {
            $this->add($component);
        }
        return $this;
    }

    /**
     * Returns item by ID.
     * Throws exception if item isn't defined.
     *
     * @api
     * @param string $id
     * @return mixed
     * @throws \Exception
     */
    public function get($id)
    {
        $this->checkDefinitionExistence($id);
        if (!$this->hasInitialized($id)) {
            $this->initialize($id);
        }
        return $this->items[$id]->getValue();
    }

    protected function &getRef($id)
    {
        $this->get($id);
        return $this->items[$id]->getValueRef();
    }

    /**
     * Set's item value.
     * Throws exception if item isn't defined or has no setter.
     *
     * @api
     * @param string $id
     * @param mixed $value
     * @return $this
     */
    public function set($id, $value)
    {
        $this->checkDefinitionExistence($id);
        $this->items[$id]->set($value);
        return $this;
    }

    /**
     * Returns true if hub contains item with specified ID, otherwise returns false.
     *
     * @api
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        return array_key_exists($id, $this->items);
    }

    public function update($id)
    {
        if ($this->hasInitialized($id)) {
            $this->initialize($id);
        }
        return $this;
    }

    protected function hasInitialized($id)
    {
        return $this->items[$id]->isInitialized();
    }

    protected function checkDefinitionExistence($id)
    {
        if (!$this->has($id)) {
            throw new NotFoundException("Item '$id' not found.");
        }
    }

    protected function initialize($id)
    {
        $item = $this->items[$id];
        $old = $item->isInitialized() ? $item->getValue() : null;
        $item->initializeValue();
        $this->extend($id);
        $this->trackTo($id);
        $this->trackFrom($id, $old);
    }

    /**
     * @return Item[]
     */
    protected function getInitializedItems()
    {
        $res = [];
        foreach ($this->items as $id => $item) {
            if ($item->isInitialized()) {
                $res[$id] = $item;
            }
        }
        return $res;
    }

    protected function isUsedByInitializedItems($id)
    {
        $item = $this->items[$id];
        foreach ($this->getInitializedItems() as $otherId => $otherItem) {
            if ($otherItem->uses($id) || $item->usedBy($otherId)) {
                return true;
            }
        }
        return false;
    }

    protected function registerDefinition(Definition $definition, ComponentInterface $component)
    {
        $id = $definition->id;
        if ($this->has($id)) {
            throw new AlreadyDefinedException("Can't define '$id'. Item already defined");
        }
        $this->items[$id] = new Item($definition, $component);

        // initialize immediately if definition used by initialized items
        if ($this->isUsedByInitializedItems($id)) {
            $this->initialize($id);
        }
    }

    protected function registerExtensions(array $extensions, ComponentInterface $component)
    {
        // merge extensions
        foreach ($extensions as $id) {
            if (!array_key_exists($id, $this->extensions)) {
                $this->extensions[$id] = [];
            }
            $this->extensions[$id][] = $component;
        }
    }

    protected function trackTo($id)
    {
        $item = $this->items[$id];

        foreach ($item->getUsedIds() as $otherId) {
            if ($this->hasInitialized($otherId)) {
                $item->useItem($otherId, $this->get($otherId));
                continue;
            }
            // will call trackFrom
            $this->get($otherId);
        }

        foreach ($this->items as $otherId => $otherItem) {
            if ($otherItem->usedBy($id)) {
                if ($this->hasInitialized($otherId)) {
                    $otherItem->useItByItem($id, $this->getRef($id));
                    continue;
                }
                // will call trackFrom
                $this->get($otherId);
            }
        }
    }

    protected function trackFrom($id, $prevValue)
    {
        $item = $this->items[$id];

        foreach ($item->getUsedByIds() as $otherId) {
            if (!$this->hasInitialized($otherId)) {
                continue;
            }
            $item->useItByItem($otherId, $this->getRef($otherId), $prevValue);
        };

        foreach ($this->items as $otherId => $otherItem) {
            if ($otherItem->uses($id) && $this->hasInitialized($otherId)) {
                $otherItem->useItem($id, $this->get($id), $prevValue);
            }
        }
    }

    protected function extend($id)
    {
        if (!array_key_exists($id, $this->extensions)) {
            return;
        }
        $value = $this->items[$id]->getValue();
        foreach ($this->extensions[$id] as $component) {
            $method = ComponentMethodNaming::extend($id);
            $component->$method($value);
        }
    }
}
