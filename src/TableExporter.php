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

    protected $acols;

    public function __construct($query, $model)
    {
        // only fillable are exportable
        $cols  = $model->getFillable();
        $acols = [];

        if (method_exists($model, 'getExportable')) {
            list ($cols, $acols) = $model->getExportable();
        }

        if (isset($acols)) {
            // these columns are being handled by the map function
            foreach($acols as $key => $class) {
                $index = array_search($key, $cols);
                if ($index !== false) {
                    unset($cols[$index]);
                }
            }
        }

        $this->query    = new \Illuminate\Database\Eloquent\Builder($query);
        $this->query->setModel($model);
        $this->headings = $cols;
        $this->acols    = $acols;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function map($item): array
    {
        $rst   = [];
        $data  = $item->toArray();

        // use acols to determine if data will be array or string
        foreach($this->acols as $k => $class) {
            $da = $item->{$k};

            // string array are pipe delimited
            if (strpos($class, 'StringArray:') !== false) {
                $sep = str_replace('StringArray:', '', $class);
                $da  = implode($sep, $da);
            } elseif (is_object($da)) {
                // object get convert to array
                $da = json_decode(json_encode($da), true);
            }

            // if it's an object array then convert to string
            if ($class === 'ObjectArray' && !is_string($da)) {
                $da = json_encode($da);
            }

            $data[$k] = $da;
        }

        $attrs = array_dot($data);
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
