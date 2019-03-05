<?php namespace RestExtension;
use CodeIgniter\HTTP\Request;
use RestExtension\Filter\Operators;
use RestExtension\Filter\QueryFilter;
use RestExtension\Includes\QueryInclude;
use RestExtension\Ordering\QueryOrder;

/**
 * Created by PhpStorm.
 * User: martin
 * Date: 2018-12-04
 * Time: 12:07
 *
 * @property Request $request
 * @property QueryFilter[][] $filters
 * @property QueryFilter[][] $searchFilters
 * @property QueryInclude[] $includes
 * @property QueryOrder[] $ordering
 * @property int $limit
 * @property int $offset
 * @property int $count
 */
class QueryParser {

    /*
     * This uses the RFS filter method
     *
     * Example
     * GET /items?filter=price:>=10,price:<=100.
     * This will filter items with price greater than or equal to 10 AND lower than or equal to 100. (>=10 & <=100)
     *
     * Filters are made up of rules, with a property, a value and an operator. Where property is the field you want to
     * filter against, e.g. featured, value is what you want to match e.g. true and operator is most commonly 'equals' e.g. :.
     *
     * When specifying a filter, the property and value are always separated by a colon :.
     * If no other operator is provided, then this is a simple 'equals' comparison.
     *
     * featured:true - return all posts where the featured property is equal to true.
     *
     * You can also do a 'not equals' query, by adding the 'not' operator - after the colon.
     * For example, if you wanted to find all posts which have an image,
     * you could look for all posts where image is not null: feature_image:-null.
     *
     * Filters with multiple rules
     * You can combine rules using either 'and' or 'or'. If you'd like to find all posts that are either featured,
     * or they have an image, then you can combine these two rules with a comma , which represents 'or':
     * filter=featured:true,feature_image:-null.
     *
     * If you're looking for all published posts which are not static pages,
     * then you can combine the two rules with a plus + which represents 'and': filter=status:published+page:false.
     * This is the default query performed by the posts endpoint.
     *
     * Syntax Reference
     * A filter expression is a string which provides the property, operator and value in the form property:operatorvalue:
     *
     *  -  property - a path representing the key to filter on
     *  -  : - separator between property and an operator-value expression
     *      -  operator is optional, so : on its own is roughly =
     *
     * Property
     * Matches: [a-zA-Z_][a-zA-Z0-9_.]
     *
     *  - can contain only alpha-numeric characters and _
     *  - cannot contain whitespace
     *  - must start with a letter
     *  - supports . separated paths, E.g. authors.slug or posts.count
     *  - is always lowercase, but accepts and converts uppercase
     *
     * Value
     * Can be one of the following
     *
     *  - null
     *  - true
     *  - false
     *  - a _number _(integer)
     *  - a literal
     *       - Any character string which follows these rules:
     *       - Cannot start with - but may contain it
     *       - Cannot contain any of these symbols: '"+,()><=[] unless they are escaped
     *       - Cannot contain whitespace
     *  - a string
     *       - ' string here ' Any character except a single or double quote surrounded by single quotes
     *       - Single or Double quote _MUST _be escaped*
     *       - Can contain whitespace
     *       - A string can contain a date any format that can be understood by new Date()
     *
     * Operators
     *  -   not operator
     *  >   greater than operator
     *  >=  greater than or equals operator
     *  <   less than operator
     *  <=  less than or equals operator
     *
     * Combinations
     *  + - represents and OBS! Not supported
     *  , - represents or
     *  ( filter expression ) - overrides operator precedence OBS! Not supported
     *  [] - grouping fpr IN style, ex. tags:[first-tag,second-tag]
     *
     *
     * More info https://api.ghost.org/docs/filter
     *
     */

    private $includes = [];
    private $filters = [];
    private $searchFilters = [];
    private $ordering = [];
    private $limit = null;
    private $offset = null;
    private $count = null;

    public function parseRequest(Request $request) {
        $this->request = $request;

        $includes = $request->getGet('include');
        if($includes) $this->parseInclude($request->getGet('include'));
        $filter = $request->getGet('filter');
        if($filter) $this->parseFilter($request->getGet('filter'));
        $ordering = $request->getGet('ordering');
        if($ordering) $this->parseOrdering($request->getGet('ordering'));

        $this->limit = $request->getGet('limit');
        $this->offset = $request->getGet('offset');
        $this->count = $request->getGet('count');
    }

    public static function parse($line) {
        $item = new QueryParser();
        parse_str($line, $params);

        if(isset($params['include'])) $item->parseInclude($params['include']);
        if(isset($params['filter'])) $item->parseFilter($params['filter']);
        if(isset($params['ordering'])) $item->parseOrdering($params['ordering']);

        if(isset($params['limit'])) $item->limit = $params['limit'];
        if(isset($params['offset'])) $item->offset = $params['offset'];
        if(isset($params['count'])) $item->count = $params['count'];

        return $item;
    }


    public function parseInclude(string $value) {
        foreach(explode(',', $value) as $line) {
            $this->includes[] = QueryInclude::parse($line);
        }
    }

    public function parseOrdering(string $value) {
        foreach(explode(',', $value) as $line) {
            $this->ordering[] = QueryOrder::parse($line);
        }
    }


    public function parseFilter(string $line) {
        $filters = [];
        $buffer = '';
        $inSquareBracket = false;
        $inString = false;
        for($i = 0 ; $i < strlen($line) ; $i++) {
            $char = substr($line, $i, 1);

            if(in_array($char, ['[', ']'])) $inSquareBracket = !$inSquareBracket;
            if(in_array($char, ['"', "'"])) $inString = !$inString;

            if($char == ',' &! $inSquareBracket &! $inString) {
                $filters[] = $buffer;
                $buffer = '';
            } else
                $buffer .= $char;
        }
        if(strlen($buffer))
            $filters[] = $buffer;

        foreach($filters as $filter) {
            $item = QueryFilter::parse($filter);
            $this->pushFilter($item->property, $item);
        }
    }

    /**
     * @param string $name
     * @param Filter\QueryFilter $filter
     */
    private function pushFilter($name, $filter) {
        switch($filter->operator) {
            case Operators::Search:
                if(!isset($this->searchFilters[$name])) $this->searchFilters[$name] = [];
                $this->searchFilters[$name][] = $filter;
                break;
            default:
                if(!isset($this->filters[$name])) $this->filters[$name] = [];
                $this->filters[$name][] = $filter;
        }
    }

    public function delFilter($name) {
        unset($this->filters[$name]);
    }

    public function hasFilter($name): bool {
        return isset($this->filters[$name]);
    }

    /**
     * @param $name
     * @return QueryFilter[]
     */
    public function getFilter($name) {
        return $this->filters[$name];
    }

    /**
     * @return QueryFilter[]
     */
    public function getFilters() {
        $all = [];
        foreach($this->filters as $name => $filters)
            foreach($filters as $filter)
                $all[] = $filter;
        return $all;
    }

    /**
     * @return QueryFilter[]
     */
    public function getSearchFilters() {
        $all = [];
        foreach($this->searchFilters as $name => $filters)
            foreach($filters as $filter)
                $all[] = $filter;
        return $all;
    }

    public function getOffset(): int {
        return $this->offset;
    }

    public function hasOffset(): bool {
        return !is_null($this->offset);
    }

    public function getLimit(): int {
        return $this->limit;
    }

    public function hasLimit(): bool {
        return !is_null($this->limit);
    }

    public function isCount(): bool {
        return !is_null($this->count);
    }

    public function getCount(): bool {
        return $this->count;
    }

    public function getIncludes() {
        return $this->includes;
    }

    public function getOrdering() {
        return $this->ordering;
    }

    public function hasInclude(string $name): bool {
        foreach($this->includes as $include) {
            if($include->property == $name)
                return true;
        }
        return false;
    }

    public function getInclude(string $name): QueryInclude {
        foreach($this->includes as $include) {
            if($include->property == $name)
                return $include;
        }
        return null;
    }

    public function delInclude(string $name) {
        for($i = 0 ; $i < count($this->includes) ; $i++) {
            if($this->includes[$i]->property == $name) {
                unset($this->includes[$i]);
                return;
            }
        }
    }


}