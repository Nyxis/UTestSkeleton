<?php
    /**
     * Auto-generated test by sfUTestSkeletonPlugin
     * run this test with : "php symfony test:unit {{ class }}"
     */

    include dirname(__FILE__).'{{ bootstrap }}/bootstrap/unit.php';

    /**
     * Mock class to access protected methods
     */
    class {{ class }}Mock extends {{ class }} {
        public function __call($name, $args) {
            if (method_exists($this,$name)) { return call_user_func_array(array($this,$name), $args); }
            throw new BadMethodCallException('Invalid method '.$name);
        }
    }

    {% if fixtures is defined %}// loads data
    $loader = new sfPropelData();
    {% for fixture in fixtures %}$loader->loadData(__DIR__ . '{{ bootstrap }}/../fixtures/{{ fixture }}');{% endfor %}


    {% endif %}$t = new lime_test();
    ${{ class|lower }} = new {{ class }}Mock();

    {% if methods|length > 0 %}{% for method in methods %}//---------------------------------------------------------------
    // {{ class }}::{{ method.name }}()
    //---------------------------------------------------------------
    $t->info('{{ class }}::{{ method.name }}()');
    $t->todo('::{{ method.name }}() have to be tested !');

    // takes : {{ method.parameters|join(', ') }}
    // returns : {{ method.return|join(', ') }}

    // $t->ok(${{ class|lower }}->{{ method.name }}(), '::{{ method.name }}() ... ');

    {% for exception in method.exceptions %}try {
        $msg = '::{{ method.name }}() triggers an "{{ exception }}" when ...';
        ${{ class|lower }}->{{ method.name }}();
        $t->fail($msg);
    }
    catch({{ exception }} $e) { $t->pass($msg); }

    {% endfor %}{% endfor %}
    {% else %}// Any methods have been registered to be generated.
    // You have to add an "@Utest" tag in your method comments to generate unit tests skeleton.
    {% endif %}