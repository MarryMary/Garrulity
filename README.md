# Garrulity
Garrulity is O/R Mapper for PHP  

```
$result = Q::table('welcome')->select('*')
                             ->where('hello', 'world')
                             ->and('query', 'builder')
                             ->or('Garrulity', 'O/RMapper')
                             ->top();
```
