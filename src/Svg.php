<?php

namespace BladeSvg;

use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Contracts\Support\Htmlable;

class Svg implements Htmlable
{
    private $imageName;
    private $renderMode;
    private $factory;
    private $attrs = [];

    protected $height;
    protected $width;

    public function __construct($imageName, $renderMode, $factory, $attrs = [])
    {
        $this->imageName = $imageName;
        $this->renderMode = $renderMode;
        $this->factory = $factory;
        $this->attrs = $attrs;
    }

    public function toHtml()
    {
        return new HtmlString(call_user_func([
            'inline' => [$this, 'renderInline'],
            'sprite' => [$this, 'renderFromSprite'],
        ][$this->renderMode]));
    }

    public function __call($method, $args)
    {
        if (count($args) === 0) {
            $this->attrs[] = Str::snake($method, '-');
        } else {
            $this->attrs[Str::snake($method, '-')] = $args[0];
        }
        return $this;
    }

    public function inline()
    {
        $this->renderMode = 'inline';
        return $this;
    }

    public function sprite()
    {
        $this->renderMode = 'sprite';
        return $this;
    }

    public function replaceWidthHeightWithViewbox()
    {
        $svg = $this->factory->getSvg($this->imageName);

        $dom = new \DOMDocument();

        @$dom->loadHTML($svg);

        $el = $dom->getElementsByTagName('svg')->item(0);

        if ($el->hasAttributes()) {
            foreach ($el->attributes as $attr) {
                $name = $attr->nodeName;
                $value = $attr->nodeValue;

                if ($name == 'width')
                    $this->width = $value;

                if ($name == 'height')
                    $this->height = $value;

                if ($name == 'viewBox')
                    $this->viewBoxExists = true;
            }

            if ($this->height && $this->width) {
                $el->removeAttribute('width');
                $el->removeAttribute('height');

                // Add viewBox Attribute
                $el->setAttribute('viewBox', '0 0 ' . $this->width . ' ' . $this->height);
            }
        }

        //dd($dom->saveHTML($el));

        return $dom->saveHTML($el);
    }

    public function renderInline()
    {
        $alteredSvg = $this->replaceWidthHeightWithViewbox();

        return str_replace(
            '<svg',
            sprintf('<svg%s', $this->renderAttributes()),
            $alteredSvg
        );
    }

    public function renderFromSprite()
    {
        return vsprintf('<svg%s><use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="%s#%s"></use></svg>', [
            $this->renderAttributes(),
            $this->factory->spritesheetUrl(),
            $this->factory->spriteId($this->imageName)
        ]);
    }

    private function renderAttributes()
    {
        if (count($this->attrs) == 0) {
            return '';
        }

        return ' ' . collect($this->attrs)->map(function ($value, $attr) {
                if (is_int($attr)) {
                    return $value;
                }
                return sprintf('%s="%s"', $attr, $value);
            })->implode(' ');
    }
}
