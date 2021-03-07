<?php


require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;

class Id
{
    private static $id = 3;

    public static function getId()
    {
        return dechex(self::$id++);
    }
}

class Item implements JsonSerializable
{
    private $controller;
    private $filename;
    private $id;

    /**
     * Item constructor.
     * @param $controller
     * @param $filename
     * @param array $childrens
     */
    public function __construct($filename, $controller, $childs = [])
    {
        $this->id = Id::getId();
        $this->controller = $controller;
        $this->filename = $filename;
        $this->childs = $childs;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getChilds()
    {
        return is_array($this->childs) ? $this->childs : [];
    }

    public function makeChilds($tree)
    {
        foreach ($this->getChilds() as $child) {
            if (array_key_exists($child, $tree)) {
                ProjectReader::makeLink($this, ProjectReader::makeChild($tree[$child]), $tree);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'key' => $this->id,
            'controller' => (string)$this->controller,
            'filename' => (string)$this->filename
        ];
    }
}

class Link implements JsonSerializable
{
    private $from;
    private $to;

    /**
     * Link constructor.
     * @param $from
     * @param $to
     */
    public function __construct($from, $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'from' => $this->from,
            'to' => $this->to
        ];
    }
}

class ProjectReader
{
    private static $items = [];
    private static $links = [];
    private $foldersToRead = [];
    private $fileHandler;

    /**
     * @param $item
     * @return Item
     */
    public static function makeChild($item)
    {
        $item = new Item($item['filename'], $item['controller'], $item['includes']);
        self::$items[] = $item;
        return $item;
    }

    /**
     * @param $item
     * @return Link
     */
    public static function makeLink(Item $form, Item $to, $tree)
    {
        $to->makeChilds($tree);
        $link = new Link($form->getId(), $to->getId());
        self::$links[] = $link;
        return $link;
    }

    public function getData()
    {
        return [
            "class" => "go.GraphLinksModel",
            "copiesArrays" => false,
            "copiesArrayObjects" => false,
            "nodeDataArray" => ProjectReader::$items,
            "linkDataArray" => ProjectReader::$links,
        ];
    }

    public function addFolder($dir)
    {
        $this->foldersToRead[] = new RecursiveDirectoryIterator($dir);
    }

    private function getIterator()
    {
        $iterator = new AppendIterator();
        foreach ($this->foldersToRead as $dir) {
            $iterator->append(new RecursiveIteratorIterator($dir));
        }
        return $iterator;
    }

    public static function getTemplates($crawler, $func)
    {
        $a = array_filter($crawler->filter('script')->each(function (Crawler $node, $i) use ($func) {
            return $func($node);
        }));

        return array_filter($a);
    }


    public static function getControllers(Crawler $crawler, $attrNames)
    {
        $a = array_filter($crawler->filter('div')->each(function (Crawler $node, $i) use ($attrNames) {
            $controllers = [];
            foreach ($attrNames as $attrName) {
                $controllers[] = $node->attr($attrName);
            }
            return implode('', array_filter($controllers));
        }));
        return implode('', $a);
    }

    public static function getIncludes(Crawler $crawler, $tags, $attrNames)
    {
        $includes = [];
        foreach ($tags as $tag) {

            $includesList = $crawler->filter($tag)->each(function (Crawler $node, $i) use ($attrNames) {
                $includes = [];
                foreach ($attrNames as $attrName) {
                    $includes[] = $node->attr($attrName);
                }
                return implode('', array_filter($includes));
            });
            $includes = array_merge($includes, array_filter($includesList));
        }
        return $includes;
    }

    private function getParent($tree, $filename)
    {
        $parents = [];
        foreach ($tree as $key => $files) {
            foreach ($files['includes'] as $include) {
                if ($include === $filename) {
                    $parents[] = $tree[$key];
                }
            }
        }
        return $parents;
    }

    public function run()
    {
        $flatList = [];

        foreach ($this->getIterator() as $file) {
            if ($file->isDir()) {
                continue;
            }
            if ($this->fileHandler) {
                ($this->fileHandler)($file, $flatList);
            }
        }

        $itemsWithoutParents = [];

        foreach ($flatList as $filename => $item) {
            $parent = $this->getParent($flatList, $filename);
            if (count($parent) == 0) {
                $itemsWithoutParents[] = ProjectReader::makeChild($item);
            }
        }

        foreach ($itemsWithoutParents as $item) {
            $item->makeChilds($flatList);
        }
    }

    public function setFileHandler($func)
    {
        $this->fileHandler = $func;
    }

    public function saveToFile($name)
    {
        file_put_contents($name, json_encode($this->getData(), JSON_PRETTY_PRINT));
    }
}
