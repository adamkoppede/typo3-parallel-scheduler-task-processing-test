use std::{
    io::{stderr, stdout, Write},
    mem::MaybeUninit,
    process::{Command, ExitCode, ExitStatus, Stdio},
    sync::mpsc::{channel, Receiver, Sender},
    thread::{available_parallelism, spawn},
    time::{Duration, Instant},
};

struct Invoker {
    trigger_sender: Sender<()>,
    completion_receiver: Receiver<(Duration, ExitStatus)>,
}

impl Invoker {
    pub fn new() -> Self {
        let (trigger_sender, trigger_receiver) = channel();
        let (completion_sender, completion_receiver) = channel();
        spawn(move || {
            while let Ok(..) = trigger_receiver.recv() {
                let start = Instant::now();
                let status = Command::new("vendor/bin/typo3")
                    .arg("scheduler:run")
                    .stdin(Stdio::null())
                    .status()
                    .expect("failed to run vendor/bin/typo3 scheduler:run");
                let duration = start.elapsed();
                if let Err(..) = completion_sender.send((duration, status)) {
                    break;
                }
            }
        });
        Self {
            trigger_sender,
            completion_receiver,
        }
    }
}

fn main() -> ExitCode {
    let mut round_number = 1_u64;
    let mut invokers = {
        let mut invokers = Box::<[Invoker]>::new_uninit_slice(
            available_parallelism()
                .expect("failed to determine available parallelism")
                .into(),
        );
        for slot in &mut invokers {
            *slot = MaybeUninit::new(Invoker::new());
        }
        unsafe { invokers.assume_init() }
    };

    let mut stdout_lock = stdout().lock();
    loop {
        writeln!(stdout_lock, "starting round {round_number}").expect("failed to write to stdout");

        // burst typo3 scheduler:run
        {
            for invoker in &mut invokers {
                invoker
                    .trigger_sender
                    .send(())
                    .expect("failed to trigger invoker");
            }
            for invoker in &mut invokers {
                let (duration, exit_status) = invoker
                    .completion_receiver
                    .recv()
                    .expect("failed to receive invoker completion");
                writeln!(
                    stdout_lock,
                    "\tscheduler:run complete with status {exit_status} after {} ms",
                    duration.as_millis()
                )
                .expect("failed to write to stdout");
            }
        }

        // check serialized completions
        {
            let mut command = Command::new("mysql")
                .stdin(Stdio::piped())
                .stdout(Stdio::piped())
                .stderr(Stdio::piped())
                .spawn()
                .expect("failed to start mysql client");
            let mut input = command
                .stdin
                .take()
                .expect("failed to take mysql client input pipe");
            input
                .write_all(b"select uid, serialized_executions from tx_scheduler_task;")
                .expect("failed to write sql command into mysql client input pipe");
            input
                .flush()
                .expect("failed to flush sql command into mysql client input pipe");
            drop(input);
            let output = command
                .wait_with_output()
                .expect("failed to wait for mysql client completion");
            if !output.status.success() {
                panic!(
                    "mysql client error: {}",
                    String::from_utf8_lossy(&output.stderr)
                );
            }
            let mut maybe_stderr_lock: Option<_> = None;
            for task_uid in String::from_utf8_lossy(&output.stdout)
                .lines()
                .skip(1) // skip header line
                .filter_map(|line| {
                    let mut line_tokens = line.split_whitespace();
                    let Some(uid_part) = line_tokens.next() else {
                        panic!("unexpected empty line yielded by mysql client");
                    };
                    let task_uid: u64 = uid_part
                        .parse()
                        .expect("unrecognizable uid value yielded by mysql client");
                    match line_tokens.next() {
                        None => None,
                        Some(..) => Some(task_uid),
                    }
                })
            {
                let stderr_lock = maybe_stderr_lock.get_or_insert_with(|| stderr().lock());
                stderr_lock
                    .write_all(
                        format!("Found task that is still being executed: {task_uid}\n").as_bytes(),
                    )
                    .expect("failed to write to stderr");
            }

            if maybe_stderr_lock.is_some() {
                break ExitCode::FAILURE;
            }
        }

        round_number = round_number.wrapping_add(1);
    }
}
