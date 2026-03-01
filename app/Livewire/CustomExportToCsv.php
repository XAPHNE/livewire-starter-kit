<?php

namespace App\Livewire;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Writer\CSV\{Options, Writer};
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use PowerComponents\LivewirePowerGrid\Components\Exports\Contracts\ExportInterface;
use PowerComponents\LivewirePowerGrid\Components\Exports\Export;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomExportToCsv extends Export implements ExportInterface
{
    /**
     * @throws WriterNotOpenedException|IOException
     */
    public function download(Exportable|array $exportOptions): BinaryFileResponse
    {
        $deleteFileAfterSend = boolval(data_get($exportOptions, 'deleteFileAfterSend'));
        $this->build($exportOptions);

        return response()
            ->download(storage_path($this->fileName.'.csv'))
            ->deleteFileAfterSend($deleteFileAfterSend);
    }

    /**
     * @throws WriterNotOpenedException|IOException
     */
    public function build(Exportable|array $exportOptions): void
    {
        $stripTags = boolval(data_get($exportOptions, 'stripTags', false));
        $data = $this->prepare($this->data, $this->columns, $stripTags);

        $csvSeparator = strval(data_get($exportOptions, 'csvSeparator', ','));
        $csvDelimiter = strval(data_get($exportOptions, 'csvDelimiter', '"'));

        $csvOptions = new Options($csvSeparator, $csvDelimiter);

        $writer = new Writer($csvOptions);
        $writer->openToFile(storage_path($this->fileName.'.csv'));

        $row = Row::fromValues(array_values($data['headers']));

        $writer->addRow($row);

        /** @var array<string> $row */
        foreach ($data['rows'] as $rowData) {
            $row = Row::fromValues(array_values($rowData));
            $writer->addRow($row);
        }

        $writer->close();
    }
}
