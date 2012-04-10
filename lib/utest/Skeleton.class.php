<?php

/**
 * UTestSkeleton core class
 */
class Skeleton extends Misc
{
    /**
     * setup defaults configs
     * @uses parent::configure()
     * @return Skeleton
     */
    public function setup()
    {
        return $this->configure(array(
            'php_unit' => array(
                'classname' => array(
                    'pattern' => '*.php',
                    'regex' => '/^([\w]+)\.php$/'
                ),
                'template' => array(
                    'file' => 'php_unit.twig.html'
                )
            ),
            'lime' => array(
                'classname' => array(
                    'pattern' => '*.class.php',
                    'regex' => '/^([\w]+)\.class\.php$/'
                ),
                'template' => array(
                    'file' => 'lime.twig.html'
                )
            )
        ));
    }

    /**
     * valids all parameters are corrects
     * @return bool
     * @throws InvalidArgumentException
     */
    protected function checkup()
    {
        if (!$this->bound('find_class_path')) {
            throw new Exception(sprintf('Event "%s" has to de defined and must return class path from a classname',
                'find_class_path'
            ));
        }

        return true;
    }

    /**
     * define used test engine
     * @param string $testEngine test engine to use {'php_unit', 'lime'}
     * @return Skeleton
     * @uses parent::configure()
     */
    public function useTestEngine($testEngine)
    {
        return $this->configure(
            $this->getConfOrEx($testEngine)
        );
    }

    /**
     * runs skeleton generation for classes at params
     * @param array $classes class list
     * @return Skeleton
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function run($classes)
    {
        $this->checkup();

        $classList = $this->extract($classes);

        foreach ($classList as $classname) {
            $this->save(
                $classname,
                $this->render(
                    $this->parse($classname)
                )
            );
        }

        return $this;
    }

    /**
     * get class names from dir
     * @param string $dirOrClass directory path or class name
     * @return array
     */
    protected function extract($dirOrClass)
    {
        if (is_dir($dirOrClass)) {
            $this->trigger('log', 'read-dir', sprintf('read "%s" directory', $dirOrClass));

            $classFileList = glob(sprintf('%s%s',
                $dirOrClass, $this->getConfOrEx('classname', 'pattern')
            ));

            if(empty($classFileList)) {
                throw new InvalidArgumentException(sprintf('Directory "%s" contains no php classes', $dirOrClass));
            }

            $classes = array();
            foreach ($classFileList as $classFile) {
                $classes[] = preg_filter(
                    $this->getConfOrEx('classname', 'regex'),
                    '$1', basename($classFile), 1
                );
            }
        }
        else {
            if (!class_exists($dirOrClass)) {
                throw new InvalidArgumentException(sprintf('Class "%s" does not exists', $dirOrClass));
            }

            $classes = array($dirOrClass);
        }

        return $classes;
    }

    /**
     * returns class infos, like comment's tags, classe and method name etc...
     * @param string $classname
     * @return array
     */
    protected function parse($classname)
    {
        $this->trigger('log', 'parsing', sprintf('Parsing class "%s"', $classname));

        $infos = array();
    	$reflectionClass = new ReflectionClass($classname);

    	// Set name
    	$infos['class'] = $reflectionClass->getName();
        $infos['methods'] = array();

        // fixtures
        if (preg_match_all('/\* \@fixtures ([\w\.\/]+)/', $reflectionClass->getDocComment(), $matches, PREG_SET_ORDER)) {

            $infos['fixtures'] = array();
            foreach($matches as $lineMatch) {
                $infos['fixtures'][] = $lineMatch[1];
            }
        }

    	// Manage method of class
    	$methods = @$reflectionClass->getMethods();

		$infosMethods = array();
    	foreach ($methods as $reflectionMethod) {

            $infosMethods = array(
                'name' => $reflectionMethod->getName(),
                'parameters' => array(),
                'return' => array(),
                'exceptions' => array()
            );

            $comments = $reflectionMethod->getDocComment();
            if(preg_match_all('/\* \@([a-z]+) ([\w]+)( \$[\w]+)?/', $comments, $matches, PREG_SET_ORDER)) {
                foreach($matches as $matchLine) {
                    switch($matchLine[1]) {
                        case 'param':
                            $key = 'parameters';
                            break;

                        case 'return':
                            $key = 'return';
                            break;

                        case 'throws':
                            $key = 'exceptions';
                            break;

                        default:
                            break;
                    }

                    if(!empty($key)) {
                        $infosMethods[$key][] = $matchLine[2];
                    }
                }
            }

            // Set infos method
            $infos['methods'][] = $infosMethods;
    	}

		return $infos;
    }


    /**
     * @Twig_Environment
     */
    protected $twig;

    /**
     * returns Twig engine
     * @return Twig_Environment
     */
    protected function getTwig()
    {
        if (!empty($this->twig)) {
            return $this->twig;
        }

        $this->twig = new Twig_Environment(
            new Twig_Loader_Filesystem(dirname(__FILE__).'/skeleton'), array(
                'autoescape'       => false,
                'strict_variables' => true
            )
        );

        return $this->twig;
    }

    /**
     * returns test file skeleton
     * @return mixed
     */
    protected function getSkeleton()
    {
        return $this->getTwig()->loadTemplate(
            $this->getConfOrEx('template', 'file')
        );
    }

    /**
     * injects tags var in skeleton and returns it
     * @param mixed $tags
     * @return string
     */
    protected function render($tags)
    {
        return $this->getSkeleton()->render(array_replace_recursive(array(
            'bootstrap' => str_repeat('/..', $this->treeLevel)
        ), $tags));
    }

    /**
     * generate php unit test file for class in parameter
     * @param string $classname
     * @param mixed $tags
     */
    protected function save($classname, $content)
    {
        $testPath = $this->buildPath($classname);

        if (!file_put_contents($testPath, $content)) {
            throw new RuntimeException(sprintf('Error while writting "%s" test file at path "%s".',
                $classname, $testPath
            ));
        }

        $this->trigger('log', 'file+', realpath($testPath));

        return true;
    }

    /**
     * @var int
     */
    protected $treeLevel = 0;

    /**
     * build the test path for the class at param
     * @param string $classname
     * @return string path
     */
    protected function buildPath($classname)
    {
        // find class path in symfony autoload to build same dirs in tests
        $classpath = $this->trigger('find_class_path', $classname);

        // move backward in file system to find a test dir
        $i = 0;
        $stackdir = array();

        do {
            $localTestDir = dirname($classpath).str_repeat('/..', $i);

            if(is_dir($localTestDir.'/test/')) {
                $testDir = $localTestDir;
            }
            elseif($localTestDir == sfConfig::get('sf_root_dir')) {
                $testDir = sfConfig::get('sf_test_dir');
            }
            else {
                array_push($stackdir, preg_filter('#^.+\/([A-Za-z0-9_]+)$#', '$1', dirname($localTestDir)));
            }

            $i++;
        } while(empty($testDir));

        $this->treeLevel = 0;
        $testDir = realpath($testDir).'/test/unit';
        foreach ($stackdir as $dir) {
            if ($dir == 'lib') {
                continue;
            }

            $testDir .= '/'.$dir;

            if (!is_dir($testDir)) {
                if (!mkdir($testDir)) {
                    throw new RuntimeException(sprintf('Error while creating a directory at path "%s".',
                        $testDir
                    ));
                }

                $this->trigger('log', 'dir+', $testDir);
            }

            $this->treeLevel++;
        }

        $testPath = sprintf('%s/%sTest.gen.php',
            $testDir, ucfirst($classname)
        );

        return $testPath;
    }

}
