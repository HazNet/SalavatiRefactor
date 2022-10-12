<?php

class User
{
    # Scopes

    /**
     * Scope first or fail user.
     *
     * @param  $query
     * @return bool
     */
    public function scopeFirstOrFailUser($query)
    {
        if ($query->first()) {
//            return $query->first();
            return true;
        }

        return false;
        // TODO Throw Exception (Optional)
    }
}