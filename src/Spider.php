<?php

namespace AndersonDRosa\Spider;

class Spider
{
    public $data = [];
    private $rules = [];
    private $renderedRules = [];

    public function __construct(array $data)
    {
        $this->types = [
            'cmd' => 'command',
            'fn' => 'functions',
            'str' => 'string',
            'mtc' => 'mustache',
        ];
    }

    public function getAllRules()
    {
        return $this->renderedRules;
    }

    public function addRule($name, $pattern, \Closure $fn)
    {
        if (!preg_match('#^(?:\.(?:[a-zA-Z_][a-zA-Z_0-9]*|[0-9]+|\*))+$#', '.' . $pattern)) {
            dd('Rule pattern is not valid');
        }

        $pattern = str_replace('.', '\.', $pattern);
        $pattern = str_replace('*', '.*', $pattern);

        $this->rules[$pattern] = [
            'name' => $name,
            'callback' => $fn,
        ];

        return $this;
    }

    public function getValue($path, array $param = null)
    {
        if (!preg_match('|[a-zA-Z0-9-_.\(\)]+|', $path)) {
            throw new \Exception("dont match valid characters", 1);
        }

        preg_match_all('#\.([a-zA-Z_][a-zA-Z_0-9]*)|\(([0-9]+)\)#x', '.' . $path, $m);

        $fields = [];

        foreach ($m[2] as $i => $n) {
            $fields[] = $n === '' ? $m[1][$i] : $n;
        }

        $path = implode('.', $fields);

        $first = array_shift($fields);

        if (array_key_exists($first, $this->shortcuts)) {
            $first = $this->shortcuts[$first];
        }

        $root = $this->data[$first];

        $var = $root;

        foreach ($fields as $key) {

            if (is_array($var)) {
                if (array_key_exists($key, $var)) {
                    $var = $var[$key];
                    continue;
                }
            }

            if (is_object($var)) {
                if (property_exists($var, $key)) {
                    $var = $var->{$key};
                    continue;
                }
            }

            return;
        }

        foreach ($this->rules as $rule => $row) {

            $pattern = '|^' . $rule . '$|x';

            if (preg_match($pattern, $path)) {
                @call_user_func_array($row['callback'], [$var]);
            }
        }

        return $var;
    }

    public function query($content, array $param = [])
    {
        $pattern = '#{((?:(?:\.?[a-z]+|\([0-9]+\)))+)}#Ux';

        $self = $this;

        $response = preg_replace_callback($pattern, function ($m) use ($self) {

            $path = $m[1];

            return $self->getValue($path);

        }, $content);

        return $response;
    }
}
