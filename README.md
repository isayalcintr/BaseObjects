# BaseObjects Package

`isayalcintr/baseobjects` paketi, Laravel projelerinde kullanılmak üzere **BaseObject** ve **BaseFilterObject** sınıflarını sağlar. Bu sınıflar, repository pattern ve filtreleme işlemleri için yapı taşları oluşturur.

## 🚀 Kurulum

Paketinizi projenize eklemek için:

```sh
composer require isayalcintr/baseobjects
```

Daha sonra, aşağıdaki komutu çalıştırarak sınıfları ```App/Objects``` dizinine kopyalayabilirsiniz:

```sh
php artisan vendor:publish --tag=base-objects
```

## 📚 Kullanım

### BaseObject Kullanımı

`BaseObject` sınıfı, temel nesne işlemleri için kullanılabilir. Örneğin, bir kullanıcı nesnesi oluşturmak ve bu nesneyi bir diziden başlatmak için:

```php
use isayalcintr\BaseObjects\BaseObject;

class UserObject extends BaseObject
{
    private ?string $name = null;
    private ?string $email = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }
}

// Kullanım
$user = (new UserObject())->initFromArray([
    'name' => 'John Doe',
    'email' => 'john.doe@example.com'
]);

print_r($user->toArray());
```

```php
use isayalcintr\BaseObjects\BaseFilterObject;
use Illuminate\Database\Eloquent\Builder;

class UserFilter extends BaseFilterObject
{
    protected ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function filterToName(Builder $query): Builder
    {
        return $query->where('name', 'like', '%' . $this->name . '%');
    }

    public function isFilterableName(): bool
    {
        return !is_null($this->name);
    }
}

// Kullanım
$query = \App\Models\User::query();
$filter = (new UserFilter())->setName('John');
$query = $filter->apply($query);

$users = $query->get();
print_r($users->toArray());
```