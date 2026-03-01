<?php

namespace App\Livewire;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use PowerComponents\LivewirePowerGrid\Components\Exports\Contracts\ExportInterface;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomExportToXLS extends Export implements ExportInterface
{
    /**
     * @throws \Exception
     */
    public function download(Exportable|array $exportOptions): BinaryFileResponse
    {
        $deleteFileAfterSend = boolval(data_get($exportOptions, 'deleteFileAfterSend'));
        $this->striped = strval(data_get($exportOptions, 'striped'));

        /** @var array $columnWidth */
        $columnWidth = data_get($exportOptions, 'columnWidth', []);
        $this->columnWidth = $columnWidth;

        $this->build($exportOptions);

        return response()
            ->download(storage_path($this->fileName.'.xlsx'))
            ->deleteFileAfterSend($deleteFileAfterSend);
    }

    /**
     * @throws WriterNotOpenedException|IOException
     */
    public function build(Exportable|array $exportOptions): void
    {
        $stripTags = boolval(data_get($exportOptions, 'stripTags', false));
        $data = $this->prepare($this->data, $this->columns, $stripTags);

        $options = new Options();
        $writer = new Writer($options);

        $writer->openToFile(storage_path($this->fileName.'.xlsx'));

        $style = (new Style())
            ->withFontBold(true)
            ->withFontSize(12)
            ->withShouldWrapText(false)
            ->withBackgroundColor('d0d3d8');

        $headers = [];
        foreach ($data['headers'] as $header) {
            $headers[] = Cell::fromValue($header, $style);
        }
        $writer->addRow(new Row($headers));

        /**
         * @var int<1, max> $column
         * @var float $width
         */
        foreach ($this->columnWidth as $column => $width) {
            $options->setColumnWidth($width, $column);
        }

        $default = (new Style())
            ->withFontSize(12);

        $gray = (new Style())
            ->withFontSize(12)
            ->withBackgroundColor($this->striped);

        /** @var array<string> $row */
        foreach ($data['rows'] as $key => $rowData) {
            if (count($rowData)) {
                $cells = [];
                $useGray = ($key % 2 && $this->striped);
                foreach ($rowData as $val) {
                    $cells[] = Cell::fromValue($val, $useGray ? $gray : $default);
                }
                $writer->addRow(new Row($cells));
            }
        }

        $writer->close();
    }
}
