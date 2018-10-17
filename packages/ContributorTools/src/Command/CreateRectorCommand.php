<?php declare(strict_types=1);

namespace Rector\ContributorTools\Command;

use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name\FullyQualified;
use Rector\Console\ConsoleStyle;
use Rector\ContributorTools\Configuration\Configuration;
use Rector\ContributorTools\Configuration\ConfigurationFactory;
use Rector\Printer\BetterStandardPrinter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symplify\PackageBuilder\Console\Command\CommandNaming;
use Symplify\PackageBuilder\Console\ShellCode;
use Symplify\PackageBuilder\FileSystem\FinderSanitizer;
use function Safe\getcwd;
use function Safe\sprintf;

final class CreateRectorCommand extends Command
{
    /**
     * @var string
     */
    private const TEMPLATES_DIRECTORY = __DIR__ . '/../../templates';

    /**
     * @var ConsoleStyle
     */
    private $consoleStyle;

    /**
     * @var ConfigurationFactory
     */
    private $configurationFactory;

    /**
     * @var BetterStandardPrinter
     */
    private $betterStandardPrinter;

    /**
     * @var FinderSanitizer
     */
    private $finderSanitizer;

    public function __construct(
        ConsoleStyle $consoleStyle,
        ConfigurationFactory $configurationFactory,
        BetterStandardPrinter $betterStandardPrinter,
        FinderSanitizer $finderSanitizer
    ) {
        parent::__construct();
        $this->consoleStyle = $consoleStyle;
        $this->configurationFactory = $configurationFactory;
        $this->betterStandardPrinter = $betterStandardPrinter;
        $this->finderSanitizer = $finderSanitizer;
    }

    protected function configure(): void
    {
        $this->setName(CommandNaming::classToName(self::class));
        $this->setDescription('Create a new Rector, in proper location, with new tests');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = $this->configurationFactory->createFromConfigFile(getcwd() . '/create-rector.yml');
        $data = $this->prepareData($configuration);

        $finder = Finder::create()->files()
            ->in(self::TEMPLATES_DIRECTORY);
        $smartFileInfos = $this->finderSanitizer->sanitize($finder);

        $testCasePath = null;
        foreach ($smartFileInfos as $smartFileInfo) {
            $destination = $smartFileInfo->getRelativeFilePathFromDirectory(self::TEMPLATES_DIRECTORY);
            $destination = $this->applyData($destination, $data);

            $content = FileSystem::read($smartFileInfo->getRealPath());
            $content = $this->applyData($content, $data);

            if (Strings::endsWith($destination, 'Test.php')) {
                $testCasePath = dirname($destination);
            }

            FileSystem::write($destination, $content);

            $this->consoleStyle->note(sprintf('New file "%s" was generated', $destination));
        }

        // @todo make Rector class clickable in CLI output, so we can just jump right in
        // probably absolute path might help
        $this->consoleStyle->success(sprintf(
            'New Rector "%s" is ready!%sNow make these tests green again:%svendor/bin/phpunit %s',
            $configuration->getName(),
            PHP_EOL . PHP_EOL,
            PHP_EOL,
            $testCasePath
        ));

        return ShellCode::SUCCESS;
    }

    /**
     * @return mixed[]
     */
    private function prepareData(Configuration $configuration): array
    {
        $data = [
            '_Package_' => $configuration->getPackage(),
            '_Category_' => $configuration->getCategory(),
            '_Description_' => $configuration->getDescription(),
            '_Name_' => $configuration->getName(),
            '_CodeBefore_' => trim($configuration->getCodeBefore()) . PHP_EOL,
            '_CodeBeforeExample_' => $this->prepareCodeForDefinition($configuration->getCodeBefore()),
            '_CodeAfter_' => trim($configuration->getCodeAfter()) . PHP_EOL,
            '_CodeAfterExample_' => $this->prepareCodeForDefinition($configuration->getCodeAfter()),
            '_Source_' => $this->prepareSourceDocBlock($configuration->getSource()),
        ];

        $arrayNodes = [];
        foreach ($configuration->getNodeTypes() as $nodeType) {
            $arrayNodes[] = new ArrayItem(new ClassConstFetch(new FullyQualified($nodeType), 'class'));
        }
        $data['_NodeTypes_Php_'] = $this->betterStandardPrinter->prettyPrint([new Array_($arrayNodes)]);

        $data['_NodeTypes_Doc_'] = '\\' . implode('|\\', $configuration->getNodeTypes());

        return $data;
    }

    /**
     * @param mixed[] $data
     */
    private function applyData(string $content, array $data): string
    {
        return str_replace(array_keys($data), array_values($data), $content);
    }

    private function prepareCodeForDefinition(string $code): string
    {
        if (Strings::contains($code, PHP_EOL)) {
            // multi lines
            return sprintf("<<<'CODE_SAMPLE'%s%sCODE_SAMPLE%s", PHP_EOL, $code, PHP_EOL);
        }

        // single line
        return "'" . str_replace("'", '"', $code) . "'";
    }

    private function prepareSourceDocBlock(string $source): string
    {
        if (! $source) {
            return $source;
        }

        $sourceDocBlock = <<<'CODE_SAMPLE'
/**
 * @see %s
 */
CODE_SAMPLE;

        return sprintf($sourceDocBlock, $source);
    }
}
