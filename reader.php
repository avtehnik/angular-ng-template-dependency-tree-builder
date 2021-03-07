<?php

require_once __DIR__ . '/ProjectReader.php';

use Symfony\Component\DomCrawler\Crawler;

$projectReader = new ProjectReader();


//path to your project
$projectDir = '/Users/username/projects/app/';

//add folders to scan
$projectReader->addFolder($projectDir . 'public/html/');
$projectReader->addFolder($projectDir . 'app/');

//tune your custom file reader, paths etc...

$projectReader->setFileHandler(function (SplFileInfo $file, array &$flatList) use ($projectDir) {
    if ($file->getExtension() === 'html' || $file->getExtension() === 'php') {
        $path = $file->getPathname();
        if (strpos(file_get_contents($path), '<?php') === 0) {
            return;
        }
        $crawler = new Crawler(file_get_contents($path));
        $filename = str_replace($projectDir, '', $path);

        if (strpos($filename, 'public/') === 0) {
            $filename = str_replace('public/', '', $filename);
        }

        $templates = ProjectReader::getTemplates($crawler, function (Crawler $node) {
            if ($node->attr('type') === 'text/ng-template') {
                return $node->attr('id');
            }
            return null;
        });

        if (count($templates)) {
            foreach ($templates as $template) {
                $flatList[$filename] = [
                    'filename' => $template,
                    'controller' => '',
                    'includes' => []
                ];
            }
        }

        $flatList[$filename] = [
            'filename' => $filename,
            'controller' => ProjectReader::getControllers($crawler, ['ng-controller', 'data-ng-controller']), //attributes with controller definition
            'includes' => ProjectReader::getIncludes($crawler,
                ['nxbn-abs-include', 'div'], // tags which include templates
                [
                    'nxbn-rel-path',  // attributes with links to template
                    'data-ng-include',
                    'data-nexben-rel-path',
                    'data-nxbn-rel-path'
                ]
            )
        ];
    };
});


$projectReader->run();

$projectReader->saveToFile(__DIR__ . '/data.json');