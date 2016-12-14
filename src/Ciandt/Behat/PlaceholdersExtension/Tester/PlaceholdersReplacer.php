<?php


namespace Ciandt\Behat\PlaceholdersExtension\Tester;

use Ciandt\Behat\PlaceholdersExtension\Config\ConfigsRepository;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Behat\Tester\StepTester;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Tester\Setup\SuccessfulSetup;
use Behat\Testwork\Tester\Setup\SuccessfulTeardown;

/**
 * Tester executing step tests in the runtime.
 *
 */
class PlaceholdersReplacer implements StepTester {

    /**
     * @var StepTester
     */
    private $baseTester;

    /**
     * @var array
     */
    private $variantTags;

    /**
     * @var array
     */
    private $variant;

    /**
     * @var array
     */
    private $configsRepo;

    /**
     * @var array
     */
    private $placeholders = FALSE;

    /**
     * @var string
     */
    private $configPath;
    
    /**
     * @var string
     */
    private $configSection;

    /**
     * @var string
     */
    private $environment;

    public function __construct(StepTester $baseTester, $variantTags, ConfigsRepository $configsRepo, $environment) {
        $this->baseTester = $baseTester;
        $this->configsRepo = $configsRepo;
        $this->variantTags = $variantTags;
        $this->environment = $environment;
    }

    /**
     * {@inheritdoc}
     * @todo use the tag to get correct section, not always the default one
     */
    public function setUp(Environment $env, FeatureNode $feature, StepNode $step, $skip) {
        if ($this->configsRepo->hasTag($step->configTag)) {
            $this->configSection = 'default';
            $this->placeholders = $this->configsRepo->getConfigSection($step->configTag, $this->configSection)['placeholders'];
            $this->configPath = $this->configsRepo->getFilePath($step->configTag);
            if ($step->variant){
              $this->variant = $step->variant;
            }
            
        }
        
        return new SuccessfulSetup();
    }

    /**
     * {@inheritdoc}
     */
    public function test(Environment $env, FeatureNode $feature, StepNode $step, $skip = false) {
        $tester = $this->baseTester;
        if ($this->placeholders) {
            $result = $tester->test($env, $feature, $this->reconstructStep($step), $skip);
            return $result;
        } else {
            return $tester->test($env, $feature, $step, $skip);
        }
    }

    private function reconstructStep(StepNode $step) {
        //@todo replace placeholders on arguments (tablenode)        
        $arguments = $step->getArguments();
        $text = $this->replacePlaceholders($step->getText(), $step->variant, $this->environment);
        return new StepNode(
                $step->getKeyword(), $text, $arguments, $step->getLine(), $step->getKeywordType());
    }

    private function replacePlaceholders($string, $var, $env) {
        preg_match_all('/\${(?P<key>[^}]+)}/i', $string, $placeholders, PREG_SET_ORDER);
        foreach ($placeholders as $placeholder) {
            $key = $placeholder['key'];
            $value = $this->getReplacement($key);
            $string = str_replace('${' . $key . '}', $value, $string);
        }
        return $string;
    }

    private function getReplacement($placeholderKey) {
        
        $values = $this->placeholders;
        $configPath = $this->configPath;
        $section = $this->configSection;
        $variant = $this->variant;
        $environment = $this->environment;
        
        $keys = array('$'.$variant,'$'.$environment,$placeholderKey);
        $treePosition = "$configPath>$section>placeholders";
        
        return $this->recursivePlaceholderSearch($keys, $values, $treePosition);
    }

    private function recursivePlaceholderSearch($keys, $values, $treePosition) {
        if (empty($keys) || !is_array($values) ){
          return $values;
        }
        $key = array_pop($keys);
        if (key_exists($key, $values)){
          return $this->recursivePlaceholderSearch($keys,$values[$key],"$treePosition>$key");
        } elseif (key_exists('$default', $values)){
          return $this->recursivePlaceholderSearch($keys,$values['$default'],$treePosition.'>$default');
        } else {
          throw new \Exception("no placeholder is defined on $treePosition>[$key or \$default]");
        }
        
      }
    

    /**
     * {@inheritdoc}
     */
    public function tearDown(Environment $env, FeatureNode $feature, StepNode $step, $skip, StepResult $result) {
        return new SuccessfulTeardown();
    }

}