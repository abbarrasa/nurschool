"""Verify editor argument mapping without requiring a Docker daemon."""
import json
import os
from pathlib import Path
import subprocess
import shutil
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class PhpDockerTest(unittest.TestCase):
    def invoke(self, arguments, stdin='', exit_code=0, helper_output=False):
        with tempfile.TemporaryDirectory() as directory:
            docker = Path(directory) / 'docker'
            docker.write_text(
                '#!/usr/bin/env python3\n'
                'import json, os, sys\n'
                'payload = {"args": sys.argv[1:], "stdin": sys.stdin.read(), "cwd": os.getcwd()}\n'
                'if os.environ["FAKE_HELPER"] == "1": payload = {"path": "/var/www/nurschool/src/Kernel.php"}\n'
                'print(json.dumps(payload))\n'
                'sys.exit(int(os.environ["FAKE_EXIT"]))\n'
            )
            docker.chmod(0o755)
            return subprocess.run(
                [str(ROOT / 'bin/php-docker'), *arguments], input=stdin,
                text=True, capture_output=True, cwd='/tmp',
                env={**os.environ, 'PATH': directory + ':' + os.environ['PATH'],
                     'FAKE_EXIT': str(exit_code), 'FAKE_HELPER': str(int(helper_output))},
            )

    def test_maps_workspace_paths_and_preserves_argument_boundaries(self):
        result = self.invoke(['-l', str(ROOT / 'templates/a b.php'), '-r', 'echo "hello";'])
        self.assertEqual(result.returncode, 0, result.stderr)
        payload = json.loads(result.stdout)
        self.assertEqual(payload['cwd'], str(ROOT))
        self.assertEqual(payload['args'][-4:], ['-l', '/var/www/nurschool/templates/a b.php', '-r', 'echo "hello";'])
        self.assertIn('XDEBUG_MODE=off', payload['args'])
        self.assertIn('-T', payload['args'])

    def test_forwards_stdin_and_failure_status(self):
        result = self.invoke(['-l'], '<?php invalid syntax', 255)
        self.assertEqual(result.returncode, 255)
        self.assertEqual(json.loads(result.stdout)['stdin'], '<?php invalid syntax')

    def test_copies_extension_helpers_and_maps_returned_paths(self):
        with tempfile.TemporaryDirectory() as directory:
            version = 'moetelo.twiggy-' + Path(directory).name
            helpers = Path(directory) / version / 'dist/phpUtils'
            helpers.mkdir(parents=True)
            helper = helpers / 'definitionClassPsr4.php'
            helper.write_text('<?php // Test helper.\n')
            destination = ROOT / 'var/editor' / version
            try:
                result = self.invoke([str(helper)], helper_output=True)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(json.loads(result.stdout)['path'], str(ROOT / 'src/Kernel.php'))
                self.assertEqual((destination / 'phpUtils' / helper.name).read_text(), helper.read_text())
            finally:
                if destination.exists():
                    shutil.rmtree(destination)

    def test_preserves_paths_outside_workspace(self):
        result = self.invoke(['/tmp/other.php', str(ROOT) + '-other/file.php'])
        self.assertEqual(json.loads(result.stdout)['args'][-2:], ['/tmp/other.php', str(ROOT) + '-other/file.php'])


if __name__ == '__main__':
    unittest.main()
