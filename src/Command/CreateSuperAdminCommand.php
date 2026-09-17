<?php

namespace Nurschool\Command;

use Doctrine\DBAL\Exception as DatabaseException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nurschool\Entity\Role;
use Nurschool\Entity\User;
use Nurschool\Repository\RoleRepository;
use Nurschool\Repository\UserRepository;
use Nurschool\Validation\SuperAdminCredentialsValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create-super-admin', description: 'Crea un usuario con el rol ROLE_SUPER_ADMIN de forma interactiva.')]
final class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SuperAdminCredentialsValidator $credentials,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error('Este comando requiere interacción. Ejecútalo sin --no-interaction en una terminal.');

            return Command::INVALID;
        }

        try {
            $emailQuestion = (new Question('Email'))->setValidator($this->credentials->email(...))->setMaxAttempts(3);
            $email = (string) $io->askQuestion($emailQuestion);
            if ($this->users->findOneBy(['email' => $email]) !== null) {
                $io->error('Ya existe un usuario con ese email. No se ha modificado su cuenta.');

                return Command::FAILURE;
            }

            $passwordQuestion = $this->secretQuestion('Contraseña (mínimo 12 caracteres, máximo 72 bytes)');
            $passwordQuestion->setValidator($this->credentials->password(...));
            $password = (string) $io->askQuestion($passwordQuestion);

            $confirmation = $this->secretQuestion('Repite la contraseña');
            $confirmation->setValidator(static function (#[\SensitiveParameter] mixed $value) use ($password): string {
                if (!is_string($value) || !hash_equals($password, $value)) {
                    throw new \InvalidArgumentException('Las contraseñas no coinciden.');
                }

                return $value;
            });
            $io->askQuestion($confirmation);

            $user = (new User())->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            unset($password);

            $role = $this->roles->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
            if ($role === null) {
                $role = (new Role())->setName('ROLE_SUPER_ADMIN')->setTranslationId('app.roles.super_admin_role');
                $this->entityManager->persist($role);
            }
            $user->addRole($role);
            $this->entityManager->persist($user);
            // Doctrine flushes the role, user and join table in a single transaction.
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $io->error('El email o el rol se ha creado durante la operación. No se ha creado el usuario; vuelve a intentarlo.');

            return Command::FAILURE;
        } catch (DatabaseException) {
            $io->error('No se ha podido crear el usuario. Comprueba la conexión y las migraciones de la base de datos.');

            return Command::FAILURE;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('Usuario creado correctamente con el rol ROLE_SUPER_ADMIN.');

        return Command::SUCCESS;
    }

    private function secretQuestion(string $prompt): Question
    {
        return (new Question($prompt))
            ->setHidden(true)
            ->setHiddenFallback(false)
            ->setTrimmable(false)
            ->setMaxAttempts(3);
    }
}
