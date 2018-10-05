<?php


class SimpleGoogleDrive
{
    private $client;
    private $drive;
    private $notification = false;

    public function __construct(\Google_Client $client)
    {
        $this->client = $client;
        $this->drive = new \Google_Service_Drive($client);
    }

    public function sendNotification(bool $notification)
    {
        $this->notification = $notification;
    }

    public function upload($file, array $receivers)
    {
        $filename = $file['name'];
        $content = file_get_contents($file['tmp_name']);
        $fileMetadata = new \Google_Service_Drive_DriveFile(
            array(
                'name' => $filename,
                'mimeType' => mime_content_type($file)
            )
        );


        try {
            try {
                $file = $this->drive->files->create($fileMetadata, array(
                    'data' => $content,
                    'fields' => 'id'));
            } catch (\GuzzleHttp\Exception\ConnectException $ex) {
                return $ex->getResponse();
            }


            //Set user permission for document
            foreach ($receivers as $email => $role) {
                $permission = new \Google_Service_Drive_Permission(array(
                    'type' => 'user',
                    'role' => $role,
                    'emailAddress' => $email,
                ));
                $this->drive->permissions->create($file->id, $permission, array('fields' => 'id', 'sendNotificationEmail' => $this->notification));
            }

            $permissionOwner = new \Google_Service_Drive_Permission(array(
                'type' => 'user',
                'role' => 'writer',
                'emailAddress' => $this->client->getConfig('email'),
            ));

            $this->drive->permissions->create($file->id, $permissionOwner, array('fields' => 'id', 'sendNotificationEmail' => $this->notification));

            $permissionMain = new \Google_Service_Drive_Permission(array(
                'type' => 'user',
                'role' => 'writer',
                'emailAddress' => $this->client->getConfig('email'),
            ));

            $this->drive->permissions->create($file->id, $permissionMain, array('fields' => 'id', 'sendNotificationEmail' => $this->notification));

            $file = $this->drive->files->update($file->id, $fileMetadata, array(
                'data' => $content,
                'fields' => 'id, modifiedTime'));

            return [
                'filename' => $filename,
                'fileId' => $file->id,
                'url' =>  $file->id,
            ];
        } catch (\Google_Service_Exception $ex) {
            $exception = json_decode($ex->getMessage());
            $message = null;

            if (isset($exception->error->errors)) {
                $message = $exception->error->errors[0]->message;
            }

            return [
                'code' => $ex->getCode(),
                'message' => $message
            ];
        }
    }
}