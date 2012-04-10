<?php

/**
 * classe abstraite des fonctions utilitaires dans les objets du projet
 *
 * défini un moteur de gestion de configs
 * défini un gestionnaire d'évènements
 *
 * @package cron
 * @subpackage lib
 */
abstract class Misc
{
    //---------------------------------------------------------------
    // factories
    //---------------------------------------------------------------

    /**
     * crée et configure un nouveau job
     * @abstract
     * @uses getNew()
     * @return Tf1Job
     */
    public static function create()
    {
        $class = new static();

        return $class->setup();
    }

    /**
     * fonction de mise en route
     * @return Misc
     */
    public function setup()
    {
        return $this;
    }


    //---------------------------------------------------------------
    // configurations
    //---------------------------------------------------------------

    /** tableau de configurations de l'objet */
    protected $configs = array();

    /**
     * méthode définissant les configurations avec le tableau en paramètres
     * @param array $configs configurations à définir pour cet objet
     * @return Misc
     */
    public function configure($configs)
    {
        if(empty($configs)) {
            return $this;
        }

        // si plusieurs configure
        $this->configs = array_replace_recursive(
            $this->configs, $configs
        );

        return $this;
    }

    /**
     * efface toutes les configs
     * @return Misc
     */
    public function clearConf()
    {
        unset($this->configs);
        $this->configs = array();

        return $this;
    }


    /**
     * renvoie la configuration ayant pour clés les paramètres
     * un arg = une clé dans l'index des configs, le dernier est la val par défaut
     * @return mixed objet sous la clé en config
     */
    protected function getConf()
    {
        try
        {
            $args = func_get_args();
            $default = array_pop($args);
            return call_user_func_array(array($this, 'getConfOrEx'), $args);
        }
        catch(Exception $e)
        {
            return $default;
        }
    }

    /**
     * renvoie la config sous la clé du paramètre, lève une exception si indéfinie
     *
     * @return mixed objet sous la clé en config
     * @throws Exception si config indéfinie
     */
    protected function getConfOrEx()
    {
        $args = func_get_args();
        $evalStr = '$this->configs["'.implode('"]["', $args).'"]';
        $evalCode = 'if (!isset(%s)) { throw new Exception("%s"); } $value = %s;';

        eval(sprintf($evalCode,
            $evalStr,
            sprintf('Missing configuration : %s.%s', get_class($this), implode('.', $args)),
            $evalStr
        ));

        return $value;
    }


    //---------------------------------------------------------------
    // évènements
    //---------------------------------------------------------------

    /** pool d'évènements */
    protected $eventPool = array();

    /**
     * défini un callback sur un évènement pour cet objet
     * un seul callback peut être défini par évènement
     * @param string $event nom de l'évènement
     * @return Misc
     * @throws Exception si le callback n'est pas valide
     */
    public function bind($event, $callback)
    {
        if(!is_callable($callback)) {
            throw new Exception('Invalid callback for event "'.$event.'"');
        }

        $this->eventPool[$event] = $callback;

        return $this;
    }

    /**
     * lance l'évènement ayant pour nom le premier paramètre
     * ne fait rien si l'évènement n'est pas défini (renvoie false)
     * @param string $event évènement à lancer
     * @param mixed tous les autres arguments sont envoyés inline a²u callback
     * @return code de retour de la fonction appelée
     */
    public function trigger()
    {
        $args = func_get_args();
        if(empty($args)){
            throw new Exception('Empty trigger params, need at least an event name for first');
        }

        $event = array_shift($args);

        if(empty($this->eventPool[$event])) {
            return false;
        }

        return call_user_func_array(
            $this->eventPool[$event], $args
        );
    }

    /**
     * teste si l'évènement en paramètre est défini
     * @param string $event nom de l'évènement
     * @return bool
     */
    public function bound($event)
    {
        return !empty($this->eventPool[$event])
            && is_callable($this->eventPool[$event]);
    }

    /**
     * bind des évènements sur l'objet en paramètre, évènements issus
     * de la méthode getEvents()
     * @param Misc $listenedObject objet à écouter
     * @uses getEvents
     */
    public function listen(Misc $listenedObject)
    {
        $listeEvents = $this->getEvents();
        if(empty($listeEvents)) {
            return $this;
        }

        foreach($listeEvents as $event => $callback)
        {
            if(!is_callable($callback)) {
                continue;
            }

            $listenedObject->bind($event, $callback);
        }

        return $this;
    }

    /**
     * renvoie la liste des évènements que la classe peut écouter
     * @return array
     */
    protected function getEvents()
    {
        return array();
    }

}

?>