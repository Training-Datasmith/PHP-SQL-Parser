<?php

declare (strict_types=1);
namespace Phpsql_Parser\utils;

use Phpsql_Parser\Options;
use Phpsql_Parser\processors\Default_Processor;
class Expression_Token
{
    private $sub_tree;
    private $token;
    private $token_type;
    private $trim;
    private $upper;
    private $no_quotes;
    public function __construct($key = '', $token = '')
    {
        $this->sub_tree = false;
        $this->token = $token;
        $this->token_type = false;
        $this->trim = trim($token);
        $this->upper = strtoupper($this->trim);
        $this->no_quotes = null;
    }
    # TODO: we could replace it with a constructor new ExpressionToken(this, "*")
    public function add_token($string)
    {
        $this->token .= $string;
    }
    public function is_enclosed_within_parenthesis()
    {
        return !empty($this->upper) && $this->upper[0] === '(' && substr($this->upper, -1) === ')';
    }
    public function set_sub_tree($tree)
    {
        $this->sub_tree = $tree;
    }
    public function get_sub_tree()
    {
        return $this->sub_tree;
    }
    public function get_upper($idx = false)
    {
        return $idx !== false ? $this->upper[$idx] : $this->upper;
    }
    public function get_trim($idx = false)
    {
        return $idx !== false ? $this->trim[$idx] : $this->trim;
    }
    public function get_token($idx = false)
    {
        return $idx !== false ? $this->token[$idx] : $this->token;
    }
    public function set_no_quotes($token, $qchars, Options $options)
    {
        $this->no_quotes = $token === null ? null : $this->revoke_quotation($token, $options);
    }
    public function set_token_type($type)
    {
        $this->token_type = $type;
    }
    public function ends_with($needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        $start = $length * -1;
        return substr($this->token, $start) === $needle;
    }
    public function is_whitespace_token()
    {
        return $this->trim === '';
    }
    public function is_comma_token()
    {
        return $this->trim === ',';
    }
    public function is_variable_token()
    {
        return $this->upper[0] === '@';
    }
    public function is_sub_query_token()
    {
        return preg_match('/^\(\s*(-- [\w\s]+\n)?\s*SELECT/i', $this->trim);
    }
    public function is_expression()
    {
        return $this->token_type === Expression_Type::EXPRESSION;
    }
    public function is_bracket_expression()
    {
        return $this->token_type === Expression_Type::BRACKET_EXPRESSION;
    }
    public function is_operator()
    {
        return $this->token_type === Expression_Type::OPERATOR;
    }
    public function is_in_list()
    {
        return $this->token_type === Expression_Type::IN_LIST;
    }
    public function is_function()
    {
        return $this->token_type === Expression_Type::SIMPLE_FUNCTION;
    }
    public function is_unspecified()
    {
        return $this->token_type === false;
    }
    public function is_variable()
    {
        return $this->token_type === Expression_Type::GLOBAL_VARIABLE || $this->token_type === Expression_Type::LOCAL_VARIABLE || $this->token_type === Expression_Type::USER_VARIABLE;
    }
    public function is_aggregate_function()
    {
        return $this->token_type === Expression_Type::AGGREGATE_FUNCTION;
    }
    public function is_custom_function()
    {
        return $this->token_type === Expression_Type::CUSTOM_FUNCTION;
    }
    public function is_column_reference()
    {
        return $this->token_type === Expression_Type::COLREF;
    }
    public function is_constant()
    {
        return $this->token_type === Expression_Type::CONSTANT;
    }
    public function is_sign()
    {
        return $this->token_type === Expression_Type::SIGN;
    }
    public function is_sub_query()
    {
        return $this->token_type === Expression_Type::SUBQUERY;
    }
    private function revoke_quotation($token, Options $options)
    {
        $def_proc = new Default_Processor($options);
        return $def_proc->revoke_quotation($token);
    }
    public function to_array()
    {
        $result = [];
        $result['expr_type'] = $this->token_type;
        $result['base_expr'] = $this->token;
        if (!empty($this->no_quotes)) {
            $result['no_quotes'] = $this->no_quotes;
        }
        $result['sub_tree'] = $this->sub_tree;
        return $result;
    }
}