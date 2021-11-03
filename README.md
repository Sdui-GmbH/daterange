# SDUI daterange

### usage

#### Initializing

```
$beginsAt = new DateTime();
$endsAt = new DateTime();
$dateRange = new DateRange($beginsAt, $endsAt);
```

It may also be initialized with Carbon objects

```
new DateRange(Carbon::yesterday(), Carbon::now())
```

or from laravels helper functions

```
new DateRange(today(), now())
```


#### Gaps

It is possible to find gaps between a scope range and intersection ranges

```
/*
 * Scope            |0--------------------------24>
 * Intersection
 * Gaps             {0           				24}
*/
$start = Carbon::make('2021-11-25')->startOfDay();
$end = $start->clone()->endOfDay();
$scope = new DateRange($start, $end);
$gaps = DateRange::gaps($scope, []);

count($gaps); 			// 1
$gaps[0]->getStart(); 	// 2021-11-25 00:00:00
$gaps[0]->getEnd(); 	// 2021-11-25 23:59:59
```

More advanced

```
/*
 * Scope            |0--------------------------24>
 * Intersection     [0           9]
 * Gaps                            {9           24}
*/
$start = Carbon::make('2021-11-25')->startOfDay();
$end = $start->clone()->endOfDay();
$scope = new DateRange($start, $end);
$intersection = new DateRange($start->clone(), $start->clone()->setHour(9));
$gaps = DateRange::gaps($scope, [$intersection]);

count($gaps); 			// 1
$gaps[0]->getStart(); 	// 2021-11-25 00:00:00
$gaps[0]->getEnd(); 	// 2021-11-25 08:59:59
```