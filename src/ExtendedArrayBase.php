<?php

/**
 * PHP Version 7
 *
 * Extended Array Base File
 *
 * @category Extended_Class
 * @package  Breier\Libs
 * @author   Andre Breier <breier.de@gmail.com>
 * @license  GPLv3 https://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace Breier;

use Breier\ExtendedArrayMergeMap as MergeMap;
use SplFixedArray;
use ArrayIterator;
use ArrayObject;

/**
 * ArrayIterator Class Entities
 *
 * @property int STD_PROP_LIST  = 1;
 * Properties have their normal functionality when accessed as list
 * @property int ARRAY_AS_PROPS = 2;
 * Entries can be accessed as properties (read and write)
 *
 * @method null append(mixed $value); Append an element to the object
 * @method int count(); The amount of elements
 * @method mixed current(); Get the element under the cursor
 * @method int getFlags(); Get behaviour flags of the ArrayIterator
 * @method mixed key(); Current position element index
 * @method mixed offsetGet(mixed $index); Get element in given index
 * @method string serialize(); Applies PHP serialization to the object
 * @method null setFlags(string $flags); Set behaviour flags of ArrayIterator
 * @method null unserialize(string $serialized); Populates self properties
 * @method bool valid(); Validate element in the current position
 *
 * Extended Array Base Abstract Class to improve array handling
 */
abstract class ExtendedArrayBase extends ArrayIterator
{
    private $positionMap = [];
    private $lastCursorPosition = 0;

    /**
     * Instantiate an Extended Array
     *
     * @param mixed $array To be parsed into properties
     * @param int   $flags (STD_PROP_LIST | ARRAY_AS_PROPS)
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($array = null, int $flags = 2)
    {
        if (static::isArrayObject($array)) {
            $array = $array->getArrayCopy();
        }

        if ($array instanceof SplFixedArray) {
            $array = $array->toArray();
        }

        if (empty($array)) {
            $array = [];
        }

        if (!is_array($array)) {
            throw new \InvalidArgumentException(
                'Only array types are accepted as parameter!'
            );
        }

        foreach ($array as &$item) {
            if (is_array($item)) {
                $item = new static($item);
            }
        }

        parent::__construct($array, $flags);

        $this->updatePositionMap();

        $this->rewind();
    }

    /**
     * Converts the Extended Array to JSON String
     */
    public function __toString(): string
    {
        return $this->jsonEncode();
    }

    /**
     * Extending ASort Method to support sub-arrays
     * Sort ascending by elements
     */
    public function asort(): ExtendedArrayBase
    {
        return $this->uasort(
            function ($a, $b) {
                if (static::isArrayObject($a)) {
                    $a = $a->getArrayCopy();
                }
                if (static::isArrayObject($b)) {
                    $b = $b->getArrayCopy();
                }
                return $a < $b ? -1 : 1;
            }
        );
    }

    /**
     * Element is an alias for Current
     *
     * @return mixed
     */
    public function element()
    {
        return $this->current();
    }

    /**
     * Move the Cursor to the End, poly-fill for `end`
     */
    public function end(): ExtendedArrayBase
    {
        if ($this->count()) {
            $this->seek($this->count() - 1);
        }

        return $this;
    }

    /**
     * First is an alias for Rewind
     */
    public function first(): ExtendedArrayBase
    {
        return $this->rewind();
    }

    /**
     * Extending Get Array Copy to convert sub-items to array
     */
    public function getArrayCopy(): array
    {
        $plainArray = parent::getArrayCopy();

        foreach ($plainArray as &$item) {
            if (static::isArrayObject($item) || $item instanceof MergeMap) {
                $item = $item->getArrayCopy();
            }
        }

        return $plainArray;
    }

    /**
     * Is Array Object identifies usable classes
     *
     * @param mixed $array The object to be validated
     */
    public static function isArrayObject($array): bool
    {
        return (
            $array instanceof ExtendedArrayBase
            || $array instanceof ArrayIterator
            || $array instanceof ArrayObject
        );
    }

    /**
     * JSON Encode
     *
     * @param int $options (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | ...)
     * @param int $depth   Recursion level
     */
    public function jsonEncode(
        int $options = JSON_THROW_ON_ERROR,
        int $depth = 512
    ): string {
        return json_encode($this, $options, $depth);
    }

    /**
     * Get Keys have to be implemented
     */
    abstract public function keys();

    /**
     * Extending KSort Method to update position map
     * Sort ascending by element indexes
     */
    public function ksort(): ExtendedArrayBase
    {
        parent::ksort();

        $this->updatePositionMap();

        return $this->rewind();
    }

    /**
     * Last is an alias to End
     */
    public function last(): ExtendedArrayBase
    {
        return $this->end();
    }

    /**
     * Extending NatCaseSort Method to update position map
     * Sort elements using case insensitive 'natural order'
     */
    public function natcasesort(): ExtendedArrayBase
    {
        parent::natcasesort();

        $this->updatePositionMap();

        return $this->rewind();
    }

    /**
     * Extending NatSort Method to update position map
     * Sort elements using 'natural order'
     */
    public function natsort(): ExtendedArrayBase
    {
        parent::natsort();

        $this->updatePositionMap();

        return $this->rewind();
    }

    /**
     * Extending next Method to return ExtendedArrayBase instead of void
     */
    public function next(): ExtendedArrayBase
    {
        parent::next();

        return $this;
    }

    /**
     * Extending Offset Exists Method to behave like 'array_key_exists'
     * Validate element index
     *
     * @param mixed $index To check
     */
    public function offsetExists($index): bool
    {
        if ($index !== 0) {
            $index = $index ?: '';
        }

        return parent::offsetExists($index);
    }

    /**
     * Extending Offset Set Method to update position map
     * Set an element with index name
     *
     * @param int|string $index  Key of the item
     * @param mixed      $newval To be set
     */
    public function offsetSet($index, $newval): void
    {
        $isAppend = !is_null($index)
            ? !$this->offsetExists($index)
            : true;

        if (
            is_array($newval)
            || static::isArrayObject($newval)
            || $newval instanceof SplFixedArray
        ) {
            $newval = new static($newval);
        }

        parent::offsetSet($index, $newval);

        if ($isAppend) {
            $this->appendPositionMap($index);
        }
    }

    /**
     * Extending Offset Unset Method to update position map
     * Remove an element
     *
     * @param int|string $index Key of the item
     */
    public function offsetUnset($index): void
    {
        parent::offsetUnset($index);

        $this->saveCursor();
        $this->updatePositionMap();
        $this->restoreCursor();
    }

    /**
     * Move the Cursor to Previous element
     */
    public function prev(): ExtendedArrayBase
    {
        $currentPosition = $this->getCursorPosition();

        if (!$currentPosition) {
            return $this->end()->next();
        }

        $this->seek($currentPosition - 1);

        return $this;
    }

    /**
     * Extending Rewind Method to return ExtendedArrayBase instead of void
     * Move the cursor to initial position
     */
    public function rewind(): ExtendedArrayBase
    {
        parent::rewind();

        return $this;
    }

    /**
     * Extending UAsort Method to update position map
     * Sort by elements using given function
     *
     * @param callable $cmp_function to compare
     */
    public function uasort($cmp_function): ExtendedArrayBase
    {
        parent::uasort($cmp_function);

        $this->updatePositionMap();

        return $this->rewind();
    }

    /**
     * Extending UKsort Method to update position map
     * Sort by indexes using given function
     *
     * @param callable $cmp_function to compare
     */
    public function uksort($cmp_function): ExtendedArrayBase
    {
        parent::uksort($cmp_function);

        $this->updatePositionMap();

        return $this->rewind();
    }

    /**
     * Get Position Map
     */
    protected function getPositionMap(): array
    {
        return $this->positionMap;
    }

    /**
     * Save Current Cursor Position so it can be restored
     */
    protected function saveCursor(): void
    {
        $this->lastCursorPosition = $this->getCursorPosition();
    }

    /**
     * Restore Cursor Position
     */
    protected function restoreCursor(): void
    {
        if ($this->lastCursorPosition >= $this->count()) {
            $this->end();
            return;
        }

        $this->seek($this->lastCursorPosition);
    }

    /**
     * Get Cursor Position
     */
    private function getCursorPosition(): int
    {
        return array_search(
            $this->key(),
            $this->positionMap,
            true
        );
    }

    /**
     * Update Position Map
     */
    private function updatePositionMap(): void
    {
        $this->positionMap = [];

        for ($this->first(); $this->valid(); $this->next()) {
            array_push($this->positionMap, $this->key());
        }
    }

    /**
     * Append Position Map
     *
     * @param int|string $keyName being appended
     */
    private function appendPositionMap($keyName = null): void
    {
        if (empty($keyName)) {
            $this->saveCursor();
            $keyName = $this->last()->key();
            $this->restoreCursor();
        }

        array_push($this->positionMap, $keyName);
    }
}
