impl Context {
    fn receive_packet(
        &mut self,
    ) -> Result<(data::FrameType, u64, u32, Vec<u8>), data::EncoderStatus> {
        match self {
            Context::Eight(ref mut context) => context.receive_packet().map(|packet| {
                (packet.frame_type, packet.input_frameno, packet.data)
            }),
            Context::Sixteen(ref mut context) => context.receive_packet().map(|packet| {
                (packet.frame_type, packet.input_frameno, packet.data)
            }),
        }
    }

    fn flush(&mut self) {
        match self {
            Context::Eight(ref mut context) => context.flush(),
            Context::Sixteen(ref mut context) => context.flush(),
        }
    }
}

impl VideoEncoderImpl for Rav1Enc {
    fn set_format(
        &self,
        element: &Self::Type,
        state: &gst_video::VideoCodecState<'static, gst_video::video_codec_state::Readable>,
    ) -> Result<(), gst::LoggableError> {
        let cfg = config::Config::new().with_threads(settings.threads);
        // TODO: RateControlConfig

        *self.state.lock().unwrap() = Some(State {
            context: if video_info.format_info().depth()[0] > 8 {
                Context::Sixteen(cfg.new_context().map_err(|err| {
                    gst::loggable_error!(CAT, "Failed to create context: {:?}", err)
                })?)
            } else {
                Context::Eight(cfg.new_context().map_err(|err| {
                    gst::loggable_error!(CAT, "Failed to create context: {:?}", err)
                })?)
            },
            video_info,
        });

        let output_state = element
            .set_output_state(
                gst::Caps::builder("video/x-av1")
                    .field("stream-format", "obu-stream")
                    .field("alignment", "tu")
                    .build(),
                Some(state),
            )
            .map_err(|_| gst::loggable_error!(CAT, "Failed to set output state"))?;
        element
            .negotiate(output_state)
            .map_err(|_| gst::loggable_error!(CAT, "Failed to negotiate"))?;

        self.parent_set_format(element, state)
    }
}
