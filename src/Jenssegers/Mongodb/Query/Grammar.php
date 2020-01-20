<?php

namespace Jenssegers\Mongodb\Query;

use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;

class Grammar extends BaseGrammar
{
    /**
     * {@inheritdoc}
     */
    protected function compileWheresToArray($query)
    {
        return collect($query->wheres)->map(function ($where) use ($query) {
            $baseWhere = $this->{"where{$where['type']}"}($query, $where);

            if (isset($where['sql']) && is_array($where['sql'])) {
                $baseWhere = json_encode($baseWhere);
            }

            return $where['boolean'].' '.$baseWhere;
        })->all();
    }
}
