<?php
namespace isayalcintr\BaseObjects;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionMethod;

abstract class BaseObject
{
    private array $ignoredProperties = [];
    private array $targetProperties = [];

    public function getIgnoredProperties(): array
    {
        return $this->ignoredProperties;
    }

    public function setIgnoredProperties(...$properties): self
    {
        $this->ignoredProperties = array_map([$this, 'convertToCamelCase'], $properties);
        return $this;
    }

    public function setIgnoredProperty(...$properties): self
    {
        foreach ($properties as $property) {
            $this->ignoredProperties[] = $this->convertToCamelCase($property);
        }
        return $this;
    }

    public function getTargetProperties(): array
    {
        return $this->targetProperties;
    }

    public function setTargetProperties(...$properties): self
    {
        $this->targetProperties = array_map([$this, 'convertToCamelCase'], $properties);
        return $this;
    }

    public function setTargetProperty(...$properties): self
    {
        foreach ($properties as $property) {
            $this->targetProperties[] = $this->convertToCamelCase($property);
        }
        return $this;
    }

    public function getActiveProperties(): array
    {
        $methods = get_class_methods($this);
        $activeProperties = [];

        foreach ($methods as $method) {
            if (str_starts_with($method, 'get')) {
                $property = lcfirst(preg_replace('/^get/', '', $method));

                // Eğer targetProperties boş değilse sadece listedekileri al
                if (!empty($this->getTargetProperties()) && !in_array($property, $this->getTargetProperties())) {
                    continue;
                }

                // ignoredProperties içinde değilse, işlenebilir olarak ekle
                if (!in_array($property, $this->getIgnoredProperties())) {
                    $activeProperties[] = $property;
                }
            }
        }

        return $activeProperties;
    }

    public function toArray(): array
    {
        $result = [];
        $activeProperties = $this->getActiveProperties();

        foreach ($activeProperties as $property) {
            if (in_array($property, ['ignoredProperties', 'targetProperties', 'activeProperties', 'alias', 'column']))
                continue;
            $method = 'get' . ucfirst($property);
            if (method_exists($this, $method)) {
                $result[$property] = $this->$method();
            }
        }
        return $result;
    }

    function toArrayForSnakeCase(): array
    {
        $result = [];
        foreach ($this->toArray() as $key => $value) {
            $result[Str::snake($key)] = $value;
        }
        return $result;
    }

    public function initFromArray(array $data): static
    {
        $activeProperties = $this->getActiveProperties();

        foreach ($data as $key => $value) {
            // Snake case veriyi camel case'e çevir
            $property = $this->convertToCamelCase($key);
            // Eğer bu property aktifse (yani getActiveProperties içinde varsa), set metodunu çağır
            if (in_array($property, $activeProperties)) {
                $method = 'set' . ucfirst($property);
                if (method_exists($this, $method)) {
                    $reflectionMethod = new ReflectionMethod($this, $method);
                    $parameters = $reflectionMethod->getParameters();

                    if (!empty($parameters) && $parameters[0]->hasType()) {
                        $paramType = $parameters[0]->getType()->getName();
                        try {
                            $value = $this->convertType($value, $paramType);
                        } catch (Exception $e) {
                            throw new InvalidArgumentException("Parametre {$key} için beklenen tip: {$paramType}, verilen: " . gettype($value) . ". " . $e->getMessage());
                        }
                    }
                    $this->$method($value);
                }
            }
        }

        return $this;
    }

    public function initFromRequest(Request $request): static
    {
        $this->initFromArray($request->all());
        return $this;
    }

    /**
     * @throws Exception
     */
    protected function convertType($value, string $expectedType)
    {
        if (is_null($value)) {
            return null;
        }

        switch ($expectedType) {
            case 'int':
            case 'integer':
                if (is_numeric($value)) {
                    return (int)$value;
                }
                throw new Exception("Veri integer olarak dönüştürülemedi.");

            case 'float':
            case 'double':
                if (is_numeric($value)) {
                    return (float)$value;
                }
                throw new Exception("Veri float olarak dönüştürülemedi.");

            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? throw new Exception("Veri boolean olarak dönüştürülemedi.");

            case 'string':
                return (string)$value;

            case 'array':
                if (is_array($value)) {
                    return $value;
                }
                throw new Exception("Veri array olarak dönüştürülemedi.");

            default:
                if (class_exists($expectedType)) {
                    if ($value instanceof $expectedType) {
                        return $value;
                    }
                    throw new Exception("Veri {$expectedType} olarak dönüştürülemedi.");
                }
                throw new Exception("Bilinmeyen tip: {$expectedType}.");
        }
    }

    protected function convertToCamelCase(string $snakeString): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $snakeString))));
    }

}
