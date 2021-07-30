<?php namespace RestExtension\Fields;

class QueryField {

    public $ignoreAuto = false;
    public ?string $fieldName;

    public static function parse($field) {
        $item = new QueryField();

        if ($field == "") {
            $item->fieldName = null;
        } else {
            $item->fieldName = $field;
        }

        return $item;
    }

    public function isRelationField(): bool {
        return strpos($this->fieldName, '.') !== false;
    }

}
