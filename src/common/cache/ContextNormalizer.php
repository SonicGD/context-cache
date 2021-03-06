<?php

namespace sitkoru\contextcache\common\cache;


use directapi\components\Enum;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

class ContextNormalizer extends GetSetMethodNormalizer
{
    /**
     * @param mixed $data
     * @param null  $format
     * @return bool
     */
    public function supportsNormalization($data, $format = null): bool
    {
        return \is_object($data);
    }

    /**
     * @param mixed $data
     * @param mixed $type
     * @param null  $format
     * @return bool
     */
    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return array_key_exists('_class', $data);
    }

    /**
     * @param object $object
     * @param null   $format
     * @param array  $context
     * @return array|bool|float|int|string
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $data = parent::normalize($object, $format, $context);
        $data['_class'] = \get_class($object);
        if (\is_subclass_of($data['_class'], Enum::class)) {
            /**
             * @var Enum $object
             */
            $data['type'] = $object->__toString();
        }
        return array_filter($data, function ($value) {
            return $value !== null;
        });
    }

    /**
     * @param array|object $data
     * @param string       $class
     * @param null         $format
     * @param array        $context
     * @return object
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        if (\is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }
        if (\is_array($data) && isset($data['_class'])) {
            $class = $data['_class'];
        }
        return parent::denormalize($data, $class, $format, $context);
    }

    /**
     * @param object      $object
     * @param  string     $attribute
     * @param mixed       $value
     * @param null|string $format
     * @param array       $context
     * @throws \ReflectionException
     *
     * @return void
     */
    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = []): void
    {
        if ($attribute === '_class') {
            return;
        }
        if ($attribute === '_id') {
            return;
        }
        $class = null;
        $isArray = false;
        if (\is_array($value)) {
            if (isset($value['_class'])) {
                $class = $value['_class'];
            } else {
                $first = reset($value);
                if (\is_array($first) && isset($first['_class'])) {
                    $class = $first['_class'];
                    $isArray = true;
                }
            }
        } elseif (\is_object($value) && isset($value->_class)) {
            $class = $value->_class;
        }
        if ($class) {
            if (isset($value['type']) && \is_subclass_of($class, Enum::class)) {
                $value = new $class($value['type']);
            } elseif ($isArray) {
                $newValue = [];
                foreach ($value as $val) {
                    $val = $this->denormalize($val, $class, $format, $context);
                    $newValue[] = $val;
                }
                $value = $newValue;
            } else {
                $value = $this->denormalize($value, $class, $format, $context);
            }
        }

        $reflClass = new \ReflectionClass($object);
        foreach ($reflClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflProperty) {
            if ($reflProperty->name !== $attribute || !$reflProperty->isPublic() || $reflProperty->isStatic() || !$this->isAllowedAttribute($object,
                    $reflProperty->name, $format, $context)) {
                continue;
            }

            $object->$attribute = $value;
            return;
        }

        parent::setAttributeValue($object, $attribute, $value, $format, $context);
    }

    /**
     * @param array            $data
     * @param string           $class
     * @param array            $context
     * @param \ReflectionClass $reflectionClass
     * @param array|bool       $allowedAttributes
     * @return null|\ReflectionMethod
     */
    protected function getConstructor(
        array &$data,
        $class,
        array &$context,
        \ReflectionClass $reflectionClass,
        $allowedAttributes
    ): ?\ReflectionMethod {
        if (\is_subclass_of($class, Enum::class)) {
            return parent::getConstructor($data, $class, $context, $reflectionClass, $allowedAttributes);
        }
        return null;
    }

    /**
     * @param object $object
     * @param null   $format
     * @param array  $context
     * @return array|string[]
     * @throws \ReflectionException
     */
    protected function extractAttributes($object, $format = null, array $context = []): array
    {
        $attributes = parent::extractAttributes($object, $format, $context);

        $reflClass = new \ReflectionClass($object);
        foreach ($reflClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflProperty) {
            if ($reflProperty->isStatic() || !$this->isAllowedAttribute($object, $reflProperty->name, $format,
                    $context)) {
                continue;
            }

            $attributes[] = $reflProperty->name;
        }

        return $attributes;
    }

    /**
     * @param object $object
     * @param string $attribute
     * @param null   $format
     * @param array  $context
     * @return mixed
     * @throws \ReflectionException
     */
    protected function getAttributeValue($object, $attribute, $format = null, array $context = [])
    {
        $value = parent::getAttributeValue($object, $attribute, $format, $context);

        $reflClass = new \ReflectionClass($object);
        foreach ($reflClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflProperty) {
            if ($reflProperty->name !== $attribute || !$reflProperty->isPublic() || $reflProperty->isStatic() || !$this->isAllowedAttribute($object,
                    $reflProperty->name, $format, $context)) {
                continue;
            }
            $value = $object->$attribute;
        }

        return $value;
    }

}