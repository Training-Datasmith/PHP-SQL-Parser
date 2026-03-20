<?php

declare (strict_types=1);
/**
 * @author     mfris
 *
 */
namespace Phpsql_Parser;

/**
 *
 * @author  mfris
 * @package PHPSQLParser
 */
final class Options
{
    /**
     * @var array
     */
    private $options;
    /**
     * @const string
     */
    public const CONSISTENT_SUB_TREES = 'consistent_sub_trees';
    /**
     * @const string
     */
    public const ANSI_QUOTES = 'ansi_quotes';
    /**
     * Options constructor.
     */
    public function __construct(array $options)
    {
        $this->options = $options;
    }
    /**
     * @return bool
     */
    public function get_consistent_subtrees()
    {
        return isset($this->options[self::CONSISTENT_SUB_TREES]) && $this->options[self::CONSISTENT_SUB_TREES];
    }
    /**
     * @return bool
     */
    public function get_ansi_quotes()
    {
        return isset($this->options[self::ANSI_QUOTES]) && $this->options[self::ANSI_QUOTES];
    }
}