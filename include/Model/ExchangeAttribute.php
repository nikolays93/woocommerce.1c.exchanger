<?php

namespace NikolayS93\Exchange\Model;

use NikolayS93\Exchange\Utils;
use NikolayS93\Exchange\ORM\Collection;

/**
 * Works with woocommerce_attribute_taxonomies
 */
class ExchangeAttribute implements Interfaces\ExternalCode
{
    const EXT_ID = '_ext_ID';
    static function getExtID()
    {
        return apply_filters('ExchangeTerm::getExtID', self::EXT_ID);
    }

    /**
     * @todo
     */
    static function valid_attribute_name()
    {
        return true;
    }

    /** @var need? */
    private $id;

    private $attribute_name;
    private $attribute_label;
    private $attribute_type = 'select';
    private $attribute_orderby = 'menu_order';
    private $attribute_public = 1;

    private $ext;

    /**
     * @var array List of ExchangeTerm
     */
    private $terms;
    // private $taxonomymeta; ?

    function __construct( $args = array(), $ext = '' )
    {
        foreach (get_object_vars( (object) $args ) as $k => $arg)
        {
            if( property_exists($this, $k) ) $this->$k = $arg;
        }

        if( !$this->attribute_name ) {
            $this->attribute_name = wc_attribute_taxonomy_name(Utils::esc_cyr($this->attribute_label));
        }
        // if( strlen($this->attribute_name) >= 28 ) {
        //     $this->attribute_name = wc_attribute_taxonomy_name(Utils::esc_cyr($this->attribute_label));
        // }

        if( $ext ) $this->ext = $ext;

        $this->terms = new Collection();
    }

    function addTerm( $term )
    {
        $term->setTaxonomy( $this->attribute_name );

        /**
         * external for unique terms
         */
        $this->terms[ $term->getExternal() ] = $term;
    }

    /**
     * Object params to array
     * @return array
     */
    public function fetch()
    {
        $attribute =  array(
            'slug'          => str_replace('pa_', '', $this->attribute_name),
            'name'          => $this->attribute_label,
            'type'          => $this->attribute_type,
            'order_by'      => $this->attribute_orderby,
            'has_archives'  => $this->attribute_public,
        );

        return $attribute;
    }

    public function getSlug()
    {
        return $this->attribute_name;
    }

    public function getTerms()
    {
        return $this->terms;
    }

    /**
     * For demonstration
     */
    public function sliceTerms($start = 0, $count = 2)
    {
        $this->terms = array_slice($this->terms->fetch(), $start, $count);
    }

    public function get_id()
    {
        return (int) $this->id;
    }

    public function set_id( $id )
    {
        $this->id = (int) $id;
    }

    function getExternal()
    {
        return $this->ext;
    }

    function setExternal($ext)
    {
        $this->ext = (String) $ext;
    }

    static public function fillExistsFromDB( &$obAttributeTaxonomies ) // , $taxonomy = ''
    {
        /** @global wpdb wordpress database object */
        global $wpdb;

        /** @var boolean get data for items who not has term_id */
        // $orphaned_only = true;

        /** @var List of external code items list in database attribute context (%s='%s') */
        $taxExternals = array();
        $termExternals = array();


        foreach ($obAttributeTaxonomies as $obAttributeTaxonomy)
        {
            /**
             * Get taxonomy (attribute)
             */
            if( !$obAttributeTaxonomy->get_id() ) {
                $taxExternals[] = "`meta_value` = '". $obAttributeTaxonomy->getExternal() ."'";
            }

            /**
             * Get terms (attribute values)
             * @var ExchangeTerm $term
             * @todo maybe add parents?
             */
            foreach ($obAttributeTaxonomy->getTerms() as $obExchangeTerm)
            {
                $termExternals[] = "`meta_value` = '". $obExchangeTerm->getExternal() ."'";
            }
        }

        $results = array();

        $taxExists = array();
        if( !empty( $taxExternals ) ) {
            $exists_query = "
                SELECT meta_id, tax_id, meta_key, meta_value
                FROM {$wpdb->prefix}woocommerce_attribute_taxonomymeta
                WHERE `meta_key` = '". ExchangeTerm::getExtID() ."'
                    AND (". implode(" \t\n OR ", array_unique($taxExternals)) . ")";

            $results = $wpdb->get_results( $exists_query );
        }

        foreach ($results as $exist)
        {
            $taxExists[ $exist->meta_value ] = $exist;
        }
        $results = array();

        /**
         * Get from database
         * @var array list of objects exists from posts db
         */
        $exists  = array();
        if( !empty($termExternals) ) {
            $exists_query = "
                SELECT tm.meta_id, tm.term_id, tm.meta_value, t.name, t.slug
                FROM $wpdb->termmeta tm
                INNER JOIN $wpdb->terms t ON tm.term_id = t.term_id
                WHERE `meta_key` = '". ExchangeTerm::getExtID() ."'
                    AND (". implode(" \t\n OR ", array_unique($termExternals)) . ")";

            $results = $wpdb->get_results( $exists_query );
        }

        /**
         * Resort for convenience
         */
        foreach($results as $exist)
        {
            $exists[ $exist->meta_value ] = $exist;
        }
        $results = array();

        foreach ($obAttributeTaxonomies as &$obAttributeTaxonomy)
        {
            /**
             * Get taxonomy (attribute)
             */
            if( !empty($taxExists[ $obAttributeTaxonomy->getExternal() ]) ) {
                $obAttributeTaxonomy->set_id( $taxExists[ $obAttributeTaxonomy->getExternal() ]->tax_id );
            }

            /**
             * Get terms (attribute values)
             * @var ExchangeTerm $term
             */
            foreach ($obAttributeTaxonomy->getTerms() as &$obExchangeTerm)
            {
                $ext = $obExchangeTerm->getExternal();

                if(!empty( $exists[ $ext ] )) {
                    $obExchangeTerm->set_id( $exists[ $ext ]->term_id );
                    $obExchangeTerm->meta_id = $exists[ $ext ]->meta_id;
                }
            }
        }
    }
}