<?php

namespace Niiknow\Laratt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;

class TableExporter implements FromCollection, WithHeadings, WithMapping
{
    protected $headings;

    protected $query;

    public function __construct($query, $mainClass)
    {
        // only fillable are exportable
        $obj  = is_string($mainClass) ? new $mainClass() : $mainClass;
        $cols = $obj->getFillable();

        if (method_exists($obj, 'getExportable')) {
            $myCols = $obj->getExportable();
            $cols   = [];

            foreach($myCols as $k => $v) {
                if (strpos($v, '\\') === false) {
                    $cols[] = $v;
                } else {
                    $subObj  = new $v();
                    $subCols = $subObj->getFillable();
                    foreach($subCols as $vv) {
                        $cols[] = "$k.$vv";
                    }
                }
            }
        }

        $this->query    = $query;
        $this->headings = $cols;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function map($item): array
    {
        $rst   = [];
        $attrs = array_dot($item->toArray());
        foreach ($this->headings as $key) {
            if (isset($attrs[$key])) {
                $rst[] = $attrs[$key];
            } else {
                $rst[] = '';
            }
        }

        return $rst;
    }

    public function collection()
    {
        return $this->query->get();
    }
}
