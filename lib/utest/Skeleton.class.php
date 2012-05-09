<?php

require_once dirname(__FILE__).'/../misc/Misc.class.php';

/**
 * UTestSkeleton core class
 */
class Skeleton extends Misc
{
    /**  */
    protected $currentClassName;

    /**  */
    protected $currentClassNamespace = '';

    /**  */
    protected $currentTestNamespace = '';

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
                    'regex' => '/^([\w]+)(\.class)?\.php$/'
                ),
                'test' => array(
                    'dir' => 'Tests',
                    'dest' => '/Tests'
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
                'test' => array(
                    'dir' => 'test',
                    'dest' => '/test/unit'
                ),
                'template' => array(
                    'file' => 'lime.twig.html'
                )
            )
        ));
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
                $classes[] = $this->getClassName($classFile);
            }
        }
        elseif(is_file($dirOrClass)) {
            $this->trigger('log', 'read-file', sprintf('read "%s" file', $dirOrClass));
            $classes = array($this->getClassName($dirOrClass));
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
     * internal mapping for classpath
     */
    protected $classmap = array();

    /**
     * extract classname from classpath
     * @param string $classpath
     * @return string $classname
     */
    protected function getClassName($classpath)
    {
        $this->classname = preg_filter(
            $this->getConfOrEx('classname', 'regex'),
            '$1', basename($classpath), 1
        );

        // parse namespace
        if (preg_match('/namespace ([a-zA-Z0-9\\\]+)\;/', file_get_contents($classpath), $matches)) {
            $this->currentClassNamespace = $matches[1];
            $this->classname = $this->currentClassNamespace.'\\'.$this->classname;
        }

        $this->classmap[$this->classname] = $classpath;
        $this->bind('find_class_path', create_function('$classname', sprintf(
            '$map = %s; return isset($map[$classname]) ? $map[$classname] : "";',
            var_export($this->classmap, true)
        )));

        return $this->classname;
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
        $infos['reflection'] = $reflectionClass;
    	$infos['class'] = $reflectionClass->getShortName();
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

            if($reflectionMethod->getDeclaringClass()->getName() != $classname) {
                continue;
            }

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
        if (!is_file($classpath)) {
            throw new Exception(sprintf('Class file for "%s" class does not exist, searched at path "%s"',
                $classname, $classpath
            ));
        }

        // move backward in file system to find a test dir
        $i = 0;
        $stackdir = array();

        do {
            $localTestDir = realpath(dirname($classpath).str_repeat('/..', $i));
            $this->trigger('log', 'search in', $localTestDir, 'comment');

            if(realpath($localTestDir) == '/') {
                throw new RuntimeException(sprintf('Any "%s" dir find in "%s" siblings parents directories.',
                    $this->getConfOrEx('test', 'dir'),
                    $classpath
                ));
            }

            if(is_dir(sprintf('%s/%s/', $localTestDir, $this->getConfOrEx('test', 'dir')))) {
                $testDir = $localTestDir;
            }
            else {
                array_unshift($stackdir, preg_filter('#^.+\/([A-Za-z0-9_]+)$#', '$1', $localTestDir));
            }

            $i++;
        } while(empty($testDir));

        $this->treeLevel = 0;
        $baseDir = preg_filter('#^.+\/([A-Za-z0-9_]+)$#', '$1', realpath($testDir));
        $testDir = realpath($testDir).$this->getConfOrEx('test', 'dest');

        foreach ($stackdir as $dir) {
            if ($dir == 'lib' || $dir == $baseDir) {
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
            $testDir, ucfirst(preg_filter('#^.+\\\([A-Za-z0-9_]+)$#', '$1', $classname))
        );

        return $testPath;
    }
}