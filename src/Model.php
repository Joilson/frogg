<?php

namespace Frogg;

use Frogg\Crypto\WT;
use Frogg\Exception\UnableToSaveRecord;
use Frogg\Model\Criteria;
use Frogg\Model\ResultSet;
use Phalcon\Di;
use Phalcon\DiInterface;
use Phalcon\Mvc\Model as PhalconModel;

/**
 * Class Model
 *
 * @method static static findFirstById(int $id)
 * @method static Model\ResultSet find($params = null)
 *
 * @property Di $di
 */
class Model extends PhalconModel implements \JsonSerializable
{
    public $populator = null;

    //This option will set the id of the clone to null
    const CLONE_RM_ID = 1;
    //This option will set the id of the clone to null AND save it to the DB
    const CLONE_CREATE_NEW = 2;

    public function getResultsetClass()
    {
        return ResultSet::class;
    }

    /**
     * @param DiInterface|null $dependencyInjector
     *
     * @return \Frogg\Model\Criteria $criteria
     */
    public static function query(DiInterface $dependencyInjector = null)
    {
        $class = '\\' . get_called_class() . 'Criteria';
        if (class_exists($class)) {
            /** @var \Frogg\Model\Criteria $criteria */
            $criteria = new $class();
            $criteria->setDI($dependencyInjector ?: Di::getDefault());
            $criteria->setModelName(get_called_class());
        } else {
            $criteria = (new Criteria())->setModelName(get_called_class())->setAlias(get_called_class());
        }

        return $criteria;
    }

    /**
     * Uses reflection to get all public and protected properties (excluding those that begin with a underscore)
     * and parses their names from camelCase to snake_case to be used in Phalcon column mapping
     *
     * @return array
     */
    public function columnMap()
    {
        $columnMap = [];
        $child     = new static();
        $reflect   = new \ReflectionObject($child);
        $props     = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);
        foreach ($props as $prop) {
            if (substr($prop->getName(), 0, 1) !== '_') {
                $output             = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $prop->getName()));
                $columnMap[$output] = $prop->getName();
            }
        }

        return $columnMap;
    }

    /**
     * @param string $attribute Name of the attribute that will be used to create a permalink
     *
     * @return string A permalink formatted string
     */
    public function permalinkFor(string $attribute) : string
    {
        $tmp = new Permalink($this->$attribute);

        return $this->getNumeration($tmp->create());
    }

    /**
     * @param array $values Values that will be used to create a permalink
     *
     * @return string A permalink formatted string
     */
    public function permalinkForValues(array $values) : string
    {
        for ($i = 0; $i < count($values); $i++) {
            $values[$i] = Permalink::createSlug($values[$i]);
        }
        $value = implode('-', $values);

        return $this->getNumeration($value);
    }

    public function tokenId($key) : string
    {
        return WT::encode(['id' => $this->id], $key);
    }

    public static function getByTokenId($token, $key)
    {
        $data = WT::decode($token, $key);

        return isset($data->id) ? static::findFirstById($data->id) : false;
    }

    private function getNumeration($slug) : string
    {
        $resultset = $this->getReadConnection()->query("SELECT `permalink`
														FROM `" . $this->getSource() . "`
														WHERE `permalink` = '$slug'
														LIMIT 1");
        $i         = 1;
        $tmp       = $slug;
        while ($resultset->numRows()) {
            $slug      = $tmp . '-' . $i++;
            $resultset = $this->getReadConnection()->query("SELECT `permalink`
															FROM `" . $this->getSource() . "`
															WHERE `permalink` = '$slug'
															LIMIT 1");
        }

        return $slug;
    }

    public function jsonSerialize()
    {
        $myData  = [];
        $child   = new static();
        $reflect = new \ReflectionObject($child);
        $props   = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);
        foreach ($props as $prop) {
            $propName = $prop->getName();
            if (substr($propName, 0, 1) !== '_') {
                $myData[$propName] = $this->{$propName};
            }
        }

        return $myData;
    }

    /**
     * @param array $options Array of options found in Frogg\Model
     *
     * @return static |\Phalcon\Mvc\ModelInterface Returns a copy of the current object
     */
    public function clone($options = [])
    {
        $newObject = PhalconModel::cloneResult(
            new static(),
            $this->toArray(),
            PhalconModel::DIRTY_STATE_TRANSIENT
        );

        foreach ($options as $option) {
            switch ($option) {
                case self::CLONE_RM_ID:
                    $newObject->id = null;
                    break;
                case self::CLONE_CREATE_NEW:
                    $newObject->id = null;
                    $newObject->create();
                    break;
            }
        }

        return $newObject;
    }

    /**
     * Save the entity or throw an exception.
     * @param mixed[] $data
     * @param mixed[] $whiteList
     * @throws \Frogg\Exception\UnableToSaveRecord
     */
    public function saveOrFail(?array $data = null, ?array $whiteList = null) : void
    {
        $return = parent::save($data, $whiteList);

        if ($return === false) {
            throw new UnableToSaveRecord('Unable to save entity. Details: ' . json_encode($this->getMessages()));
        }
    }

    public function getPopulator()
    {
        return $this->populator;
    }

    public function setPopulator($populator) : self
    {
        $this->populator = $populator;

        return $this;
    }

}
